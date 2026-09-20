<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * One credential per file, never in .env and never in the SQL.
 *
 * The containers see them under /run/secrets (bind mount, read only); a CLI run
 * from the host reads ./secrets at the root of the repo, which git ignores.
 */
final class Secret
{
    public static function read(string $nom): string
    {
        foreach (self::chemins($nom) as $chemin) {
            if (!is_readable($chemin)) {
                continue;
            }
            // trailing newline: `echo mot-de-passe > fichier` always leaves one
            $valeur = trim((string) file_get_contents($chemin));
            if ($valeur !== '') {
                return $valeur;
            }
        }

        throw new RuntimeException("Secret manquant : $nom (lancer make secrets)");
    }

    /** @return list<string> */
    private static function chemins(string $nom): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $nom)) {
            throw new RuntimeException("Nom de secret invalide : $nom");
        }

        return ['/run/secrets/' . $nom, dirname(__DIR__, 3) . '/secrets/' . $nom];
    }
}
