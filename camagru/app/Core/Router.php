<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;

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

        // Toute requête POST doit présenter un jeton CSRF valide.
        if ($httpMethod === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
            (new ErrorController())->forbidden('Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
            return;
        }

        if (!empty($this->protectedRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        if (!empty($this->adminRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user']['is_admin'])) {
            (new ErrorController())->forbidden();
            return;
        }

        // null if the route is not found or PROTECTED
        $action = $this->routes[$httpMethod][$normalizedPath] ?? null;

        if ($action === null) {
            (new ErrorController())->notFound();
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
