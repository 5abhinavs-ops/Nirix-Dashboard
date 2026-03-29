<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

corsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

const DAILY_REPORTS_SPREADSHEET_ID = '1Fb7THxUnm_TRSv-tJAB1Wm8WfNhsXPnVEQOmF5TfoRw';

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        jsonResponse(['ok' => false, 'error' => 'Empty body'], 400);
    }

    $input = json_decode($raw, true);
    if (!is_array($input)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $photoUrls = $input['photoUrls'] ?? [];
    $photoCell = is_array($photoUrls)
        ? json_encode($photoUrls, JSON_UNESCAPED_UNICODE)
        : (string) $photoUrls;

    $row = [
        (string) ($input['date'] ?? ''),
        (string) ($input['vessel'] ?? ''),
        (string) ($input['reportedBy'] ?? ''),
        (string) ($input['portEngine'] ?? ''),
        (string) ($input['starboardEngine'] ?? ''),
        (string) ($input['bilge'] ?? ''),
        (string) ($input['remarks'] ?? ''),
        $photoCell,
    ];

    $client = getGoogleClient();
    $sheets = new Google\Service\Sheets($client);

    $body = new Google\Service\Sheets\ValueRange(['values' => [$row]]);

    $sheets->spreadsheets_values->append(
        DAILY_REPORTS_SPREADSHEET_ID,
        'Sheet1!A1',
        $body,
        ['valueInputOption' => 'RAW']
    );

    jsonResponse(['ok' => true, 'message' => 'Report saved']);
} catch (Throwable $e) {
    error_log('[nirix-api] ' . get_class($e) . ': ' . $e->getMessage()); jsonResponse(['ok' => false, 'error' => 'Internal server error'], 500);
}
