<?php

// Strict scalar types: no implicit conversion of arguments
declare(strict_types=1);

// The autoloader is set up after this file: the secret reader is required by hand
require_once dirname(__DIR__) . '/app/Core/Secret.php';

// Reads a required variable of .env, put in the container environment by env_file:
// missing or empty stops the boot
$env = static function (string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException("Variable d'environnement manquante : $key (voir .env.example)");
    }
    return $value;
};

// App environment
define('APP_ENV', $env('APP_ENV'));

// Database configuration (Data Source Name)
define('DB_DSN', sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $env('DB_HOST'),
    $env('DB_PORT'),
    $env('DB_NAME')
));

// Database credentials: files under secrets/, out of .env and out of the schema
define('DB_USER', \App\Core\Secret::read('db_user'));
define('DB_PASS', \App\Core\Secret::read('db_password'));

define('APP_URL', $env('APP_URL'));

define('MAIL_HOST', $env('MAIL_HOST'));
define('MAIL_PORT', (int) $env('MAIL_PORT'));
define('MAIL_FROM', $env('MAIL_FROM'));
