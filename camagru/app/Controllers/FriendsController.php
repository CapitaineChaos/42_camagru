<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class FriendsController extends Controller
{
    public function friends(): void
    {
        $this->view('friends', ['title' => 'Friends']);
    }
}
