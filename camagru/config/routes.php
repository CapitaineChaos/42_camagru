<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PrefsController;
use App\Controllers\UserController;
use App\Controllers\FriendsController;
use App\Controllers\GalleryController;
use App\Controllers\AdminController;

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
    $router->post('/gallery', [GalleryController::class, 'gallery']);

    $router->get('/profile', [UserController::class, 'profile']);
    $router->post('/profile', [UserController::class, 'profile']);

    $router->get('/friends', [FriendsController::class, 'friends']);
    $router->post('/friends', [FriendsController::class, 'friends']);

    $router->get('/admin', [AdminController::class, 'admin']);
    $router->post('/admin', [AdminController::class, 'admin']);

    $router->requireAuth('GET', '/preferences');
    $router->requireAuth('POST', '/preferences');
    $router->requireAuth('GET', '/profile');
    $router->requireAuth('POST', '/profile');
    $router->requireAuth('GET', '/friends');
    $router->requireAuth('POST', '/friends');
    $router->requireAuth('GET', '/admin');
    $router->requireAuth('POST', '/admin');

    $router->requireAdmin('GET', '/admin');
    $router->requireAdmin('POST', '/admin');
};
