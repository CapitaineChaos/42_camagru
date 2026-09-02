<?php

// No silent casting / auto cast
declare(strict_types=1);

// Load the .env file when it is readable (CLI, php -S).
// In Docker the variables are already in the environment (env_file), which wins.
$file = dirname(__DIR__, 2) . '/.env';
if (is_readable($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (getenv($key) === false) {
            putenv($key . '=' . trim(trim($value), "\"'"));
        }
    }
}

// Helper to read an environment variable, .env is the only source of truth
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

// Database credentials
define('DB_USER', $env('DB_USER'));
define('DB_PASS', $env('DB_PASS'));

define('APP_URL', $env('APP_URL'));

define('MAIL_HOST', $env('MAIL_HOST'));
define('MAIL_PORT', (int) $env('MAIL_PORT'));
define('MAIL_FROM', $env('MAIL_FROM'));
