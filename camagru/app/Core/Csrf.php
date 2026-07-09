<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jeton anti-CSRF stocké en session (pattern synchronizer token).
 * Un seul jeton par session, injecté dans chaque formulaire et vérifié
 * pour toute requête POST.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /** Retourne le jeton courant, en le générant à la première utilisation. */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Champ caché prêt à insérer dans un <form>. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Vérifie le jeton soumis contre celui en session (comparaison constante). */
    public static function check(mixed $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $token);
    }
}
