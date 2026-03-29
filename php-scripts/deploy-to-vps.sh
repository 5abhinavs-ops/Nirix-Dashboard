#!/bin/bash
# Nirix Dashboard — Deploy all PHP API scripts to VPS
# Run this script on the VPS: bash deploy-to-vps.sh
# Target: /var/www/sg-portal.nirix.online/api/

API="/var/www/sg-portal.nirix.online/api"
mkdir -p "$API"

echo "Deploying Nirix Dashboard PHP scripts to $API..."

# ─── auth.php ────────────────────────────────────────────────────────────────
cat > "$API/auth.php" << 'ENDOFFILE'
<?php
declare(strict_types=1);

require_once '/var/www/sg-portal.nirix.online/api/vendor/autoload.php';

use Google\Client;

const ALLOWED_ORIGINS = [
    'https://sg-portal.nirix.online',
    'https://5abhinavs-ops.github.io',
];

function getGoogleClient(): Client
{
    $client = new Client();
    $client->setAuthConfig('/etc/nirix-dashboard/service-account.json');
    $client->setScopes([
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/spreadsheets',
    ]);
    return $client;
}

function corsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(array $data, int $status = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
ENDOFFILE
echo "✓ auth.php"

# ─── boat-specs.php ──────────────────────────────────────────────────────────
cat > "$API/boat-specs.php" << 'ENDOFFILE'
<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

corsHeaders();

const BOAT_TECH_SPECS_SPREADSHEET_ID = '1tj-SEY7syMVY5mHhn4hYT7Ej6z_Z2pTmrSzN3wYJk4c';

try {
    $client = getGoogleClient();
    $sheets = new Google\Service\Sheets($client);

    $response = $sheets->spreadsheets_values->get(
        BOAT_TECH_SPECS_SPREADSHEET_ID,
        'Data!A:Z'
    );

    $values = $response->getValues() ?? [];
    $boatSpecs = [];

    if ($values === []) {
        jsonResponse(['ok' => true, 'data' => $boatSpecs]);
    }

    $headerRow = $values[0];
    $boatCols = [];
    for ($j = 1, $n = count($headerRow); $j < $n; $j++) {
        $name = trim((string) ($headerRow[$j] ?? ''));
        if ($name !== '') {
            $boatCols[$j] = $name;
            $boatSpecs[$name] = [];
        }
    }

    for ($i = 1, $rows = count($values); $i < $rows; $i++) {
        $row = $values[$i];
        $fieldLabel = trim((string) ($row[0] ?? ''));
        if ($fieldLabel === '') {
            continue;
        }
        foreach ($boatCols as $j => $boatName) {
            $boatSpecs[$boatName][$fieldLabel] = isset($row[$j]) ? (string) $row[$j] : '';
        }
    }

    jsonResponse(['ok' => true, 'data' => $boatSpecs]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
ENDOFFILE
echo "✓ boat-specs.php"

# ─── fleet-load.php ──────────────────────────────────────────────────────────
cat > "$API/fleet-load.php" << 'ENDOFFILE'
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
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
ENDOFFILE
echo "✓ fleet-load.php"

# ─── fleet-save.php ──────────────────────────────────────────────────────────
cat > "$API/fleet-save.php" << 'ENDOFFILE'
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
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
ENDOFFILE
echo "✓ fleet-save.php"

# ─── daily-report.php ────────────────────────────────────────────────────────
cat > "$API/daily-report.php" << 'ENDOFFILE'
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
        ['valueInputOption' => 'USER_ENTERED']
    );

    jsonResponse(['ok' => true, 'message' => 'Report saved']);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
ENDOFFILE
echo "✓ daily-report.php"

# ─── photo-upload.php ────────────────────────────────────────────────────────
cat > "$API/photo-upload.php" << 'ENDOFFILE'
<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

corsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method Not Allowed'], 405);
}

const PHOTOS_FOLDER_ID = '1oRTw_6IbcW3N9EtvXgmU2QT5U_IFXIM-';

const ALLOWED_MIME = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

try {
    if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
        jsonResponse(['ok' => false, 'error' => 'Missing photo field'], 400);
    }

    $err = (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => 'Upload failed (code ' . $err . ')'], 400);
    }

    $tmp = (string) ($_FILES['photo']['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid upload'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime === false || !in_array($mime, ALLOWED_MIME, true)) {
        jsonResponse(['ok' => false, 'error' => 'File must be jpeg, png, gif, or webp'], 400);
    }

    if (@getimagesize($tmp) === false) {
        jsonResponse(['ok' => false, 'error' => 'Not a valid image'], 400);
    }

    $originalName = (string) ($_FILES['photo']['name'] ?? 'photo.jpg');
    $originalName = preg_replace('/[^-_.a-zA-Z0-9]/', '_', $originalName) ?: 'photo.jpg';

    $content = file_get_contents($tmp);
    if ($content === false) {
        jsonResponse(['ok' => false, 'error' => 'Could not read upload'], 500);
    }

    $client = getGoogleClient();
    $drive = new Google\Service\Drive($client);

    $fileMeta = new Google\Service\Drive\DriveFile([
        'name' => $originalName,
        'parents' => [PHOTOS_FOLDER_ID],
    ]);

    $created = $drive->files->create($fileMeta, [
        'data' => $content,
        'mimeType' => $mime,
        'uploadType' => 'multipart',
        'fields' => 'id',
        'supportsAllDrives' => true,
    ]);

    $fileId = $created->getId();
    if ($fileId === null || $fileId === '') {
        jsonResponse(['ok' => false, 'error' => 'Drive did not return file id'], 500);
    }

    $permission = new Google\Service\Drive\Permission([
        'type' => 'anyone',
        'role' => 'reader',
    ]);
    $drive->permissions->create($fileId, $permission, ['fields' => 'id']);

    $thumbUrl = 'https://drive.google.com/thumbnail?id=' . $fileId . '&sz=w800';

    jsonResponse([
        'ok' => true,
        'url' => $thumbUrl,
        'fileId' => $fileId,
    ]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
ENDOFFILE
echo "✓ photo-upload.php"

echo ""
echo "All 6 files deployed to $API"
echo "Verifying..."
ls -la "$API"/*.php
