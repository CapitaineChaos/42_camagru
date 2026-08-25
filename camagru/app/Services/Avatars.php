<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** The avatars on offer: the shipped models, and the ones cut out of a montage. */
final class Avatars
{
    private const MODELES = '/public/avatars/';
    private const DOSSIER = '/storage/avatars/';
    private const COTE = 256;

    /** Names this class writes, and the only ones it may delete. */
    private const MOTIF = '/^[0-9a-f]{32}\.jpg$/';

    /** @return list<string> filenames under public/avatars, the generic one first */
    public function models(): array
    {
        $fichiers = @scandir(BASE_PATH . self::MODELES) ?: [];

        return array_values(array_filter(
            $fichiers,
            static fn (string $nom): bool => (bool) preg_match('/\.(png|jpe?g|gif|webp)$/i', $nom)
        ));
    }

    public function isModel(string $nom): bool
    {
        return in_array($nom, $this->models(), true);
    }

    /**
     * Squares a montage into an avatar of its own: deleting the montage later
     * must not take the avatar with it.
     *
     * @return string filename written under storage/avatars
     * @throws RuntimeException
     */
    public function fromMontage(string $chemin): string
    {
        $source = @imagecreatefromjpeg($chemin);
        if ($source === false) {
            throw new RuntimeException('This montage cannot be read.');
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $cote    = min($largeur, $hauteur);

        $avatar = imagecreatetruecolor(self::COTE, self::COTE);
        imagecopyresampled(
            $avatar, $source,
            0, 0,
            intdiv($largeur - $cote, 2), intdiv($hauteur - $cote, 2),
            self::COTE, self::COTE, $cote, $cote
        );
        imagedestroy($source);

        $dossier = BASE_PATH . self::DOSSIER;
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new RuntimeException('Avatar storage unavailable.');
        }

        $nom = bin2hex(random_bytes(16)) . '.jpg';
        $ecrit = imagejpeg($avatar, $dossier . $nom, 90);
        imagedestroy($avatar);

        if (!$ecrit) {
            throw new RuntimeException('Avatar could not be saved.');
        }

        return $nom;
    }

    /** Drops an avatar cut from a montage; models and uploads are left alone. */
    public function discard(string $filename): void
    {
        if (!preg_match(self::MOTIF, $filename)) {
            return;
        }

        $fichier = BASE_PATH . self::DOSSIER . $filename;
        if (is_file($fichier)) {
            unlink($fichier);
        }
    }
}
