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

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
