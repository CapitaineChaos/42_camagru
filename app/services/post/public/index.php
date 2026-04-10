<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// == Health check ==============================================================
if ($uri === 'health') {
    echo json_encode(['service' => 'post', 'status' => 'ok']);
    exit;
}

// == GET /posts =================================================================
if ($uri === 'posts' && $method === 'GET') {
    $dbPath = '/var/www/html/data/post.sqlite';

    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // The service owns its schema - created here on first request
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            username   TEXT    NOT NULL,
            image_url  TEXT    NOT NULL UNIQUE,
            created_at TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $rows = $pdo->query(
        'SELECT id, user_id, username, image_url, created_at FROM posts ORDER BY created_at DESC'
    )->fetchAll();

    echo json_encode($rows);
    exit;
}

// == Fallback ==================================================================
http_response_code(404);
echo json_encode(['error' => 'Not found']);
