<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class AdminController extends Controller
{
    public function admin(): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId === 0 || !(new User())->isAdmin($userId)) {
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        $this->view('admin', ['title' => 'Admin Panel']);
    }
}
