<?php

declare(strict_types=1);

namespace App\Core;

final class Settings
{
    /** @var array<string, mixed>|null */
    private static ?array $valeurs = null;

    /** @param string $chemin dotted key, such as 'auth.password_reset_ttl' */
    public static function get(string $chemin, mixed $defaut = null): mixed
    {
        $noeud = self::all();

        foreach (explode('.', $chemin) as $cle) {
            if (!is_array($noeud) || !array_key_exists($cle, $noeud)) {
                return $defaut;
            }
            $noeud = $noeud[$cle];
        }

        return $noeud;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$valeurs ??= require BASE_PATH . '/config/settings.php';
    }
}
