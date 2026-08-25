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

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);

    $router->get('/verify', [AuthController::class, 'verify']);

    $router->get('/forgot-password', [PasswordController::class, 'showForgot']);
    $router->post('/forgot-password', [PasswordController::class, 'sendReset']);

    $router->get('/reset-password', [PasswordController::class, 'showReset']);
    $router->post('/reset-password', [PasswordController::class, 'reset']);

    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/avatar', [AvatarController::class, 'show']);

    $router->get('/preferences', [PrefsController::class, 'prefs']);
    $router->post('/preferences/account', [PrefsController::class, 'account']);
    $router->post('/preferences/notifications', [PrefsController::class, 'notifications']);
    $router->post('/preferences/delete', [PrefsController::class, 'deleteAccount']);

    $router->get('/gallery', [GalleryController::class, 'gallery']);
    $router->post('/gallery/like', [GalleryController::class, 'like']);
    $router->post('/gallery/comment', [GalleryController::class, 'comment']);
    $router->post('/gallery/report', [GalleryController::class, 'report']);

    $router->get('/photobooth', [PhotoboothController::class, 'photobooth']);
    $router->post('/photobooth/capture', [PhotoboothController::class, 'capture']);

    $router->get('/photo', [PhotoController::class, 'show']);
    $router->post('/photo/delete', [PhotoController::class, 'delete']);

    $router->get('/profile', [UserController::class, 'profile']);
    $router->post('/profile/avatar', [UserController::class, 'avatar']);

    $router->get('/friends', [FriendsController::class, 'friends']);
    $router->post('/friends/request', [FriendsController::class, 'request']);
    $router->post('/friends/accept', [FriendsController::class, 'accept']);
    $router->post('/friends/remove', [FriendsController::class, 'remove']);

    $router->get('/admin', [AdminController::class, 'admin']);
    $router->post('/admin/suspend', [AdminController::class, 'suspend']);
    $router->post('/admin/report/dismiss', [AdminController::class, 'dismiss']);
    $router->post('/admin/montage/delete', [AdminController::class, 'deleteMontage']);

    $router->requireAuth('GET', '/preferences');
    $router->requireAuth('GET', '/avatar');
    $router->requireAuth('POST', '/preferences/account');
    $router->requireAuth('POST', '/preferences/notifications');
    $router->requireAuth('POST', '/preferences/delete');
    $router->requireAuth('GET', '/photobooth');
    $router->requireAuth('POST', '/photobooth/capture');
    $router->requireAuth('POST', '/photo/delete');
    $router->requireAuth('POST', '/gallery/like');
    $router->requireAuth('POST', '/gallery/comment');
    $router->requireAuth('POST', '/gallery/report');
    $router->requireAuth('GET', '/profile');
    $router->requireAuth('POST', '/profile/avatar');
    $router->requireAuth('GET', '/friends');
    $router->requireAuth('POST', '/friends/request');
    $router->requireAuth('POST', '/friends/accept');
    $router->requireAuth('POST', '/friends/remove');
    $router->requireAuth('GET', '/admin');
    $router->requireAuth('POST', '/admin/suspend');
    $router->requireAuth('POST', '/admin/report/dismiss');
    $router->requireAuth('POST', '/admin/montage/delete');

    $router->requireAdmin('GET', '/admin');
    $router->requireAdmin('POST', '/admin/suspend');
    $router->requireAdmin('POST', '/admin/report/dismiss');
    $router->requireAdmin('POST', '/admin/montage/delete');
};
