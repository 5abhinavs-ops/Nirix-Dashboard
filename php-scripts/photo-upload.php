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
    error_log('[nirix-api] ' . get_class($e) . ': ' . $e->getMessage()); jsonResponse(['ok' => false, 'error' => 'Internal server error'], 500);
}
