<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;

final class Router
{
    public const OPEN  = 'open';
    public const AUTH  = 'auth';    // an open session, else a redirect to /login
    public const ADMIN = 'admin';   // an open session with is_admin, else 403

    /** @var array<string, array<string, array{0: array{0: class-string, 1: string}, 1: string}>> method => path => [action, access] */
    private array $routes = [];

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $path, array $action, string $access = self::OPEN): void
    {
        $this->routes['GET'][$this->normalize($path)] = [$action, $access];
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $path, array $action, string $access = self::OPEN): void
    {
        $this->routes['POST'][$this->normalize($path)] = [$action, $access];
    }

    public function dispatch(string $httpMethod, string $path): void
    {
        if ($httpMethod === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
            (new ErrorController())->forbidden('Security token invalid or expired. Reload the page and try again.');
            return;
        }

        $route = $this->routes[$httpMethod][$this->normalize($path)] ?? null;

        if ($route === null) {
            (new ErrorController())->notFound();
            return;
        }

        [[$controller, $method], $access] = $route;

        if ($access !== self::OPEN && empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        if ($access === self::ADMIN && empty($_SESSION['user']['is_admin'])) {
            (new ErrorController())->forbidden();
            return;
        }

        (new $controller())->{$method}();
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
