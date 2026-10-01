<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * One credential per file, never in .env and never in the SQL. The containers
 * see them under /run/secrets (compose secrets, read only).
 */
final class Secret
{
    public static function read(string $nom): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $nom)) {
            throw new RuntimeException("Nom de secret invalide : $nom");
        }

        $chemin = '/run/secrets/' . $nom;
        // trailing newline: `echo mot-de-passe > fichier` always leaves one
        $valeur = is_readable($chemin) ? trim((string) file_get_contents($chemin)) : '';
        if ($valeur === '') {
            throw new RuntimeException("Secret manquant : $nom (lancer make secrets)");
        }

        return $valeur;
    }
}
