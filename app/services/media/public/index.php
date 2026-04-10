<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

//== Health check==============================================================
if ($uri === 'health') {
    header('Content-Type: application/json');
    echo json_encode(['service' => 'media', 'status' => 'ok']);
    exit;
}

//== Serve uploaded images : GET /uploads/{filename}==========================
if (str_starts_with($uri, 'uploads/')) {
    $filename = basename($uri);

    // Strict allowlist: letters, digits, hyphen, underscore + known image ext
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.(png|jpg|jpeg|webp|gif)$/i', $filename)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid filename.']);
        exit;
    }

    $path = '/var/www/html/uploads/' . $filename;

    if (!file_exists($path) || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File not found.']);
        exit;
    }

    $ext   = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

//== Fallback==================================================================
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
