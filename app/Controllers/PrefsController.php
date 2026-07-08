<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PrefsController extends Controller
{
    public function prefs(): void
    {
        $this->view('preferences', ['title' => 'Preferences']);
    }
}