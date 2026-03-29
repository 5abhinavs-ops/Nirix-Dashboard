<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

corsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

const FLEET_BOARD_FOLDER_ID = '1TsoVjjmYMqbA4zxAm1EIY1FaNhzMAqKN';

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        jsonResponse(['ok' => false, 'error' => 'Empty body'], 400);
    }

    json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
    }

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
    $meta = new Google\Service\Drive\DriveFile();

    if ($items !== []) {
        $fileId = $items[0]->getId();
        if ($fileId !== null && $fileId !== '') {
            $drive->files->update($fileId, $meta, [
                'data' => $raw,
                'mimeType' => 'application/json',
                'uploadType' => 'media',
            ]);
            jsonResponse(['ok' => true, 'message' => 'Saved']);
        }
    }

    $createMeta = new Google\Service\Drive\DriveFile([
        'name' => 'fleet_board_state.json',
        'parents' => [FLEET_BOARD_FOLDER_ID],
    ]);

    $drive->files->create($createMeta, [
        'data' => $raw,
        'mimeType' => 'application/json',
        'uploadType' => 'multipart',
        'fields' => 'id',
        'supportsAllDrives' => true,
    ]);

    jsonResponse(['ok' => true, 'message' => 'Saved']);
} catch (Throwable $e) {
    error_log('[nirix-api] ' . get_class($e) . ': ' . $e->getMessage()); jsonResponse(['ok' => false, 'error' => 'Internal server error'], 500);
}
