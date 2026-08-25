<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Settings;
use App\Models\User;
use App\Services\CurrentUser;

final class AvatarController extends Controller
{
    /** Serves an uploaded avatar: storage/ sits outside the DocumentRoot. */
    public function show(): void
    {
        $currentUser = new CurrentUser();
        $id = (int) ($_GET['id'] ?? 0);
        $user = $id > 0 ? (new User())->findById($id) : $currentUser->fromSession($_SESSION);
        $defaut = '/avatars/' . rawurlencode((string) Settings::get('avatars.default', 'generique.png'));

        if ($user === null) {
            $this->redirect($defaut);
        }

        $avatar = $currentUser->avatarFilename($user);

        if ($currentUser->usesModelAvatar($user)) {
            $this->redirect('/avatars/' . rawurlencode($avatar));
        }

        $path = BASE_PATH . '/storage/avatars/' . $avatar;

        if (!is_file($path)) {
            $this->redirect($defaut);
        }

        $autorises = (array) Settings::get('avatars.allowed_mime', []);
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        if (!in_array($mime, $autorises, true)) {
            http_response_code(415);
            echo 'Unsupported avatar type';
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }
}
