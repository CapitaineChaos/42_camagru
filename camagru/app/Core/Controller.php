<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\LayoutDataProvider;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = []): void
    {
        $data += (new LayoutDataProvider())->fromSession($_SESSION);

        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/app/Views/layout.php';
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function lifetimeInWords(int $seconds): string
    {
        $hours = (int) round($seconds / 3600);

        return $hours === 1 ? '1 hour' : $hours . ' hours';
    }
}
