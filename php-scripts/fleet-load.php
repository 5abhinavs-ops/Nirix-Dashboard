<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

corsHeaders();

const FLEET_BOARD_FOLDER_ID = '1TsoVjjmYMqbA4zxAm1EIY1FaNhzMAqKN';

try {
    $client = getGoogleClient();
    $drive = new Google\Service\Drive($client);

    $q = sprintf(
        "name = 'fleet_board_state.json' and '%s' in parents and trashed = false",
        FLEET_BOARD_FOLDER_ID
    );

    $list = $drive->files->listFiles([
        'q' => $q,
        'fields' => 'files(id, name)',
        'pageSize' => 5,
        'supportsAllDrives' => true,
        'includeItemsFromAllDrives' => true,
    ]);

    $items = $list->getFiles() ?? [];
    if ($items === []) {
        jsonResponse(['ok' => false, 'error' => 'No saved data yet'], 404);
    }

    $fileId = $items[0]->getId();
    if ($fileId === null || $fileId === '') {
        jsonResponse(['ok' => false, 'error' => 'No saved data yet'], 404);
    }

    $http = $client->authorize();
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media';
    $response = $http->get($url);
    $contents = (string) $response->getBody();

    $decoded = json_decode($contents, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON in saved file: ' . json_last_error_msg()], 500);
    }

    if (!is_array($decoded)) {
        jsonResponse(['ok' => false, 'error' => 'Saved file is not a JSON object'], 500);
    }

    jsonResponse(['ok' => true, 'data' => $decoded]);
} catch (Throwable $e) {
    error_log('[nirix-api] ' . get_class($e) . ': ' . $e->getMessage()); jsonResponse(['ok' => false, 'error' => 'Internal server error'], 500);
}
