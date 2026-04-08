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

require_once __DIR__ . '/../src/AuthController.php';

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Health check
if ($uri === 'health') {
    echo json_encode(['service' => 'auth', 'status' => 'ok']);
    exit;
}

// Routes
if ($method === 'POST' && $uri === 'register') {
    AuthController::register();
    exit;
}

if ($method === 'POST' && $uri === 'login') {
    AuthController::login();
    exit;
}

if ($method === 'GET' && $uri === 'me') {
    AuthController::me();
    exit;
}

if ($method === 'POST' && $uri === 'forgot') {
    AuthController::forgot();
    exit;
}

if ($method === 'POST' && $uri === 'reset') {
    AuthController::reset();
    exit;
}

if ($method === 'GET' && $uri === 'verify') {
    AuthController::verify();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
