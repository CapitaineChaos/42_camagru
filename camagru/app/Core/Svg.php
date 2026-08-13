<?php

declare(strict_types=1);

namespace App\Core;

final class Svg
{
    private const DOSSIER = '/public/images/elements/';

    public static function inline(string $nom): string
    {
        if (str_contains($nom, '..')) {
            return '';
        }

        $fichier = BASE_PATH . self::DOSSIER . $nom . '.svg';
        if (!is_file($fichier)) {
            return '';
        }

        $svg = (string) file_get_contents($fichier);

        return (string) preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg, 1);
    }
}
