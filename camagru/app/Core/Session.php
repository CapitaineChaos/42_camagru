<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        session_name((string) Settings::get('session.name', 'camagru_session'));
        ini_set('session.gc_maxlifetime', (string) (int) Settings::get('session.lifetime', 7200));
        session_set_cookie_params([
            'lifetime' => (int) Settings::get('session.cookie_lifetime', 0),
            'path'     => '/',
            'httponly' => true,
            'samesite' => (string) Settings::get('session.samesite', 'Lax'),
            'secure'   => $secure,
        ]);

        session_start();

        self::expireInactive();
        self::rotateId();
    }

    /** gc_maxlifetime collection is opportunistic. */
    private static function expireInactive(): void
    {
        $duree  = (int) Settings::get('session.lifetime', 7200);
        $dernier = (int) ($_SESSION['last_activity'] ?? 0);

        if ($duree > 0 && $dernier !== 0 && time() - $dernier > $duree) {
            $_SESSION = [];
            session_destroy();
            session_start();
        }

        $_SESSION['last_activity'] = time();
    }

    private static function rotateId(): void
    {
        $delai = (int) Settings::get('session.regenerate', 0);
        if ($delai <= 0) {
            return;
        }

        $emis = (int) ($_SESSION['id_issued_at'] ?? 0);
        if ($emis === 0 || time() - $emis > $delai) {
            session_regenerate_id(true);
            $_SESSION['id_issued_at'] = time();
        }
    }
}
