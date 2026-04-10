<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === 'health') {
    echo json_encode(['service' => 'auth', 'status' => 'ok']);
    exit;
}

if ($method === 'POST' && $uri === 'register') {
    require_once __DIR__ . '/../src/RegisterController.php';
    RegisterController::handle();
    exit;
}

if ($method === 'POST' && $uri === 'login') {
    require_once __DIR__ . '/../src/LoginController.php';
    LoginController::handle();
    exit;
}

if ($method === 'GET' && $uri === 'me') {
    require_once __DIR__ . '/../src/ProfileController.php';
    ProfileController::get();
    exit;
}

if ($method === 'PATCH' && $uri === 'me') {
    require_once __DIR__ . '/../src/ProfileController.php';
    ProfileController::update();
    exit;
}

if ($method === 'POST' && $uri === 'forgot') {
    require_once __DIR__ . '/../src/PasswordController.php';
    PasswordController::forgot();
    exit;
}

if ($method === 'POST' && $uri === 'reset') {
    require_once __DIR__ . '/../src/PasswordController.php';
    PasswordController::reset();
    exit;
}

if ($method === 'GET' && $uri === 'verify') {
    require_once __DIR__ . '/../src/VerifyController.php';
    VerifyController::handle();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);

