<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [];

    /**
     * Associative array where the keys are HTTP methods and the values
     * are arrays mapping normalized paths to a boolean indicating protection.
     * @var array<string, array<string, bool>>
     */
    private array $protectedRoutes = [];

    private array $adminRoutes = [];

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $path, array $action): void
    {
        $this->routes['GET'][$this->normalize($path)] = $action;
    }

    /**
     * Require authentication for a specific route, registering it as protected.
     *
     * @param string $method The HTTP method
     * @param string $path The route path
     * @return void
     */
    public function requireAuth(string $method, string $path): void
    {
        $this->protectedRoutes[$method][$this->normalize($path)] = true;
    }

    public function requireAdmin(string $method, string $path): void
    {
        $this->adminRoutes[$method][$this->normalize($path)] = true;
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $path, array $action): void
    {
        $this->routes['POST'][$this->normalize($path)] = $action;
    }

    public function dispatch(string $httpMethod, string $path): void
    {
        $normalizedPath = $this->normalize($path);

        if (!empty($this->protectedRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        if (!empty($this->adminRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user']['is_admin'])) {
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

        // null if the route is not found or PROTECTED
        $action = $this->routes[$httpMethod][$normalizedPath] ?? null;

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
