<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PrefsController;
use App\Controllers\UserController;
use App\Controllers\GalleryController;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);

    $router->get('/verify', [AuthController::class, 'verify']);

    $router->get('/logout', [AuthController::class, 'logout']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/preferences', [PrefsController::class, 'prefs']);
    $router->post('/preferences', [PrefsController::class, 'updatePrefs']);

    $router->get('/gallery', [GalleryController::class, 'gallery']);
    $router->get('/profile', [UserController::class, 'profile']);
};
