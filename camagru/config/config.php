<?php

// Strict scalar types: no implicit conversion of arguments
declare(strict_types=1);

// Reads a required variable of .env, put in the container environment by env_file:
// missing or empty stops the boot
$env = static function (string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException("Variable d'environnement manquante : $key (voir .env.example)");
    }
    return $value;
};

// Database configuration (Data Source Name)
define('DB_DSN', sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $env('DB_HOST'),
    $env('DB_PORT'),
    $env('DB_NAME')
));

// Database credentials, from .env like the rest
define('DB_USER', $env('DB_USER'));
define('DB_PASS', $env('DB_PASSWORD'));

define('APP_URL', $env('APP_URL'));

define('MAIL_HOST', $env('MAIL_HOST'));
define('MAIL_PORT', (int) $env('MAIL_PORT'));
define('MAIL_FROM', $env('MAIL_FROM'));
