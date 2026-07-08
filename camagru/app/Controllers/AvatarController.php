<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\CurrentUser;

final class AvatarController extends Controller
{
    public function show(): void
    {
        $currentUser = new CurrentUser();
        $user = $currentUser->fromSession($_SESSION);

        if ($user === null) {
            $this->redirect('/avatars/generique.png');
        }

        $avatar = $currentUser->avatarFilename($user);

        if ($currentUser->usesModelAvatar($user)) {
            $this->redirect('/avatars/' . rawurlencode($avatar));
        }

        $path = BASE_PATH . '/storage/avatars/' . $avatar;

        if (!is_file($path)) {
            $this->redirect('/avatars/generique.png');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            http_response_code(415);
            echo 'Unsupported avatar type';
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }
}
