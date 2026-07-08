<?php

declare(strict_types=1);

// Démarrage ou reprise session
// pour accéder à $_SESSION 
session_start();

// GLOBAL CONST
define('BASE_PATH', dirname(__DIR__));

// Récupérer DB_DSN, DB_USER, DB_PASS, APP_ENV....
require BASE_PATH . '/config/config.php';

// Autoload PSR-4 : App\Foo\Bar  ->  app/Foo/Bar.php
// pour éviter les require partout
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Router;

$router = new Router();
(require BASE_PATH . '/config/routes.php')($router);

// Délivrer la requête HTTP àu routeur pour qu'il appelle le bon controller
// et la bonne méthode
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
