<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PrefsController;
use App\Controllers\UserController;
use App\Controllers\FriendsController;
use App\Controllers\GalleryController;
use App\Controllers\PasswordController;
use App\Controllers\PhotoboothController;
use App\Controllers\PhotoController;
use App\Controllers\AdminController;
use App\Controllers\AvatarController;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);

    $router->get('/register/available', [AuthController::class, 'available']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);

    $router->get('/verify', [AuthController::class, 'verify']);

    $router->get('/forgot-password', [PasswordController::class, 'showForgot']);
    $router->post('/forgot-password', [PasswordController::class, 'sendReset']);

    $router->get('/reset-password', [PasswordController::class, 'showReset']);
    $router->post('/reset-password', [PasswordController::class, 'reset']);

    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/avatar', [AvatarController::class, 'show'], Router::AUTH);

    $router->get('/preferences', [PrefsController::class, 'prefs'], Router::AUTH);
    $router->post('/preferences/account', [PrefsController::class, 'account'], Router::AUTH);
    $router->post('/preferences/notifications', [PrefsController::class, 'notifications'], Router::AUTH);
    $router->post('/preferences/delete', [PrefsController::class, 'deleteAccount'], Router::AUTH);

    $router->get('/gallery', [GalleryController::class, 'gallery']);
    $router->post('/gallery/like', [GalleryController::class, 'like'], Router::AUTH);
    $router->post('/gallery/comment', [GalleryController::class, 'comment'], Router::AUTH);
    $router->post('/gallery/report', [GalleryController::class, 'report'], Router::AUTH);

    $router->get('/photobooth', [PhotoboothController::class, 'photobooth'], Router::AUTH);
    $router->post('/photobooth/capture', [PhotoboothController::class, 'capture'], Router::AUTH);

    $router->get('/photo', [PhotoController::class, 'show']);
    $router->post('/photo/delete', [PhotoController::class, 'delete'], Router::AUTH);

    $router->get('/profile', [UserController::class, 'profile'], Router::AUTH);
    $router->post('/profile/avatar', [UserController::class, 'avatar'], Router::AUTH);

    $router->get('/friends', [FriendsController::class, 'friends'], Router::AUTH);
    $router->post('/friends/request', [FriendsController::class, 'request'], Router::AUTH);
    $router->post('/friends/accept', [FriendsController::class, 'accept'], Router::AUTH);
    $router->post('/friends/remove', [FriendsController::class, 'remove'], Router::AUTH);

    $router->get('/admin', [AdminController::class, 'admin'], Router::ADMIN);
    $router->post('/admin/suspend', [AdminController::class, 'suspend'], Router::ADMIN);
    $router->post('/admin/report/dismiss', [AdminController::class, 'dismiss'], Router::ADMIN);
    $router->post('/admin/montage/delete', [AdminController::class, 'deleteMontage'], Router::ADMIN);
};
