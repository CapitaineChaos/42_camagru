<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class GalleryController extends Controller
{
    public function gallery(): void
    {
        $this->view('gallery', ['title' => 'Gallery']);
    }
}