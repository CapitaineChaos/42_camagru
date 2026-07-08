<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [];

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $path, array $action): void
    {
        $this->routes['GET'][$this->normalize($path)] = $action;
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $path, array $action): void
    {
        $this->routes['POST'][$this->normalize($path)] = $action;
    }

    public function dispatch(string $httpMethod, string $path): void
    {
        $action = $this->routes[$httpMethod][$this->normalize($path)] ?? null;

        if ($action === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        [$controller, $method] = $action;
        (new $controller())->{$method}();
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
