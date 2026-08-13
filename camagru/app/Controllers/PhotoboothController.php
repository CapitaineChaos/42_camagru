<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PhotoboothController extends Controller
{
    public function photobooth(): void
    {
        $this->view('photobooth', ['title' => 'Photobooth']);
    }
}
