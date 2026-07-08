<?php

// No silent casting / auto cast
declare(strict_types=1);

// Helper to read an environment variable with a default value
$env = static fn (string $key, string $default = ''): string =>
    ($v = getenv($key)) !== false ? $v : $default;

// App environment
define('APP_ENV', $env('APP_ENV', 'dev'));

// Database configuration (Data Source Name)
define('DB_DSN', sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $env('DB_HOST', 'db'),
    $env('DB_PORT', '5432'),
    $env('DB_NAME', 'camagru')
));

// Database credentials
define('DB_USER', $env('DB_USER', 'camagru'));
define('DB_PASS', $env('DB_PASS', 'camagru'));

// URL publique de l'app (pour les liens dans les emails)
define('APP_URL', $env('APP_URL', 'http://localhost:8080'));

// Configuration SMTP (MailHog en dev)
define('MAIL_HOST', $env('MAIL_HOST', 'mailhog'));
define('MAIL_PORT', (int) $env('MAIL_PORT', '1025'));
define('MAIL_FROM', $env('MAIL_FROM', 'no-reply@camagru.local'));
