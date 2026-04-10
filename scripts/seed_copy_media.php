<?php
declare(strict_types=1);

/**
 * Copie les avatars depuis /avatars (monté en lecture)
 * vers /var/www/html/uploads (volume media-uploads).
 *
 * Usage (depuis la racine) :
 *   make seed-media
 */

$src = '/avatars';
$dst = '/var/www/html/uploads';

if (!is_dir($src)) {
    fwrite(STDERR, "Dossier source $src introuvable. Lancez 'make seed-avatars' d'abord.\n");
    exit(1);
}

$files   = glob("$src/*.png") ?: [];
$copied  = 0;
$skipped = 0;

foreach ($files as $file) {
    $name   = basename($file);
    $target = "$dst/$name";

    if (file_exists($target)) {
        $skipped++;
        continue;
    }

    if (!copy($file, $target)) {
        fwrite(STDERR, "  ERREUR : impossible de copier $name\n");
        continue;
    }

    echo "  + $name\n";
    $copied++;
}

echo "\nTerminé - $copied copiés, $skipped déjà présents.\n";
