<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ErrorController extends Controller
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404', ['title' => 'Page introuvable']);
    }

    public function forbidden(string $reason = ''): void
    {
        http_response_code(403);
        $this->view('errors/403', ['title' => 'Accès refusé', 'reason' => $reason]);
    }
}
