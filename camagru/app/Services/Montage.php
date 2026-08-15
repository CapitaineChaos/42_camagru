<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use GdImage;
use RuntimeException;

/** Builds the montage server side: the browser only ever sends coordinates. */
final class Montage
{
    private const DOSSIER = '/storage/images/';

    /** Decoding beyond this stays out of memory_limit at 4 bytes per pixel. */
    private const MAX_PIXELS = 16000000;

    private Overlays $overlays;

    public function __construct(?Overlays $overlays = null)
    {
        $this->overlays = $overlays ?? new Overlays();
    }

    /** @throws RuntimeException on a source that is not a decodable image */
    public function fromDataUrl(string $dataUrl): GdImage
    {
        if (!preg_match('#^data:image/(?:jpeg|png);base64,#', $dataUrl, $entete)) {
            throw new RuntimeException('Unreadable capture.');
        }

        $binaire = base64_decode(substr($dataUrl, strlen($entete[0])), true);
        if ($binaire === false) {
            throw new RuntimeException('Unreadable capture.');
        }

        return $this->decode($binaire);
    }

    /**
     * @param array<string, mixed> $fichier one entry of $_FILES
     * @throws RuntimeException
     */
    public function fromUpload(array $fichier): GdImage
    {
        $erreur = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Image too large: ' . ini_get('upload_max_filesize') . ' at most.');
        }
        if ($erreur !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($fichier['tmp_name'] ?? ''))) {
            throw new RuntimeException('Upload failed.');
        }

        $binaire = file_get_contents((string) $fichier['tmp_name']);
        if ($binaire === false) {
            throw new RuntimeException('Upload failed.');
        }

        return $this->decode($binaire);
    }

    /**
     * @return list<array{slug: string, x: float, y: float, w: float}>
     * @throws RuntimeException
     */
    public function layers(string $json): array
    {
        $brut = json_decode($json, true);
        if (!is_array($brut) || $brut === []) {
            throw new RuntimeException('Pick at least one overlay.');
        }
        if (count($brut) > (int) Settings::get('photobooth.max_layers', 8)) {
            throw new RuntimeException('Too many overlays on this montage.');
        }

        $minimum = (float) Settings::get('photobooth.min_scale', 0.05);
        $maximum = (float) Settings::get('photobooth.max_scale', 2.0);

        $calques = [];
        foreach ($brut as $calque) {
            if (!is_array($calque) || $this->overlays->path((string) ($calque['o'] ?? '')) === null) {
                throw new RuntimeException('Unknown overlay.');
            }
            $calques[] = [
                'slug' => (string) $calque['o'],
                'x'    => $this->borne((float) ($calque['x'] ?? .5), 0, 1),
                'y'    => $this->borne((float) ($calque['y'] ?? .5), 0, 1),
                'w'    => $this->borne((float) ($calque['w'] ?? .5), $minimum, $maximum),
            ];
        }

        return $calques;
    }

    /**
     * @param list<array{slug: string, x: float, y: float, w: float}> $calques
     * @return string filename written under storage/images
     */
    public function compose(GdImage $source, array $calques): string
    {
        $largeur = (int) Settings::get('photobooth.width', 800);
        $hauteur = (int) Settings::get('photobooth.height', 600);

        $montage = $this->cover($source, $largeur, $hauteur);
        imagedestroy($source);

        imagealphablending($montage, true);
        foreach ($calques as $calque) {
            $this->apply($montage, $calque, $largeur, $hauteur);
        }

        $dossier = BASE_PATH . self::DOSSIER;
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new RuntimeException('Montage storage unavailable.');
        }

        $nom = bin2hex(random_bytes(16)) . '.jpg';
        $ecrit = imagejpeg($montage, $dossier . $nom, (int) Settings::get('photobooth.quality', 85));
        imagedestroy($montage);

        if (!$ecrit) {
            throw new RuntimeException('Montage could not be saved.');
        }

        return $nom;
    }

    /** @return string|null absolute path, null when the name does not point to a montage */
    public function path(string $filename): ?string
    {
        if ($filename === '' || basename($filename) !== $filename) {
            return null;
        }

        $fichier = BASE_PATH . self::DOSSIER . $filename;

        return is_file($fichier) ? $fichier : null;
    }

    public function remove(string $filename): void
    {
        $fichier = $this->path($filename);
        if ($fichier !== null) {
            unlink($fichier);
        }
    }

    private function decode(string $binaire): GdImage
    {
        if (strlen($binaire) > (int) Settings::get('photobooth.max_source', 6291456)) {
            throw new RuntimeException('Image too large.');
        }

        $mesures = @getimagesizefromstring($binaire);
        $autorises = (array) Settings::get('photobooth.allowed_mime', []);
        if ($mesures === false || !in_array($mesures['mime'] ?? '', $autorises, true)) {
            throw new RuntimeException('Only JPEG, PNG and GIF images are accepted.');
        }
        if ($mesures[0] * $mesures[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Image resolution too high.');
        }

        $image = @imagecreatefromstring($binaire);
        if ($image === false) {
            throw new RuntimeException('Unreadable image.');
        }

        return $image;
    }

    /** Fills the frame without distortion: the overflowing side is cropped, centred. */
    private function cover(GdImage $source, int $largeur, int $hauteur): GdImage
    {
        $sourceLargeur = imagesx($source);
        $sourceHauteur = imagesy($source);
        $ratio = $largeur / $hauteur;

        $prise = [0, 0, $sourceLargeur, $sourceHauteur];
        if ($sourceLargeur / $sourceHauteur > $ratio) {
            $prise[2] = (int) round($sourceHauteur * $ratio);
            $prise[0] = intdiv($sourceLargeur - $prise[2], 2);
        } else {
            $prise[3] = (int) round($sourceLargeur / $ratio);
            $prise[1] = intdiv($sourceHauteur - $prise[3], 2);
        }

        $montage = imagecreatetruecolor($largeur, $hauteur);
        imagecopyresampled($montage, $source, 0, 0, $prise[0], $prise[1],
            $largeur, $hauteur, $prise[2], $prise[3]);

        return $montage;
    }

    /** @param array{slug: string, x: float, y: float, w: float} $calque */
    private function apply(GdImage $montage, array $calque, int $largeur, int $hauteur): void
    {
        $fichier = $this->overlays->path($calque['slug']);
        if ($fichier === null) {
            return;
        }

        $overlay = @imagecreatefrompng($fichier);
        if ($overlay === false) {
            return;
        }

        $cible = max(1, (int) round($calque['w'] * $largeur));
        $echelle = $cible / imagesx($overlay);
        $hauteurCible = max(1, (int) round(imagesy($overlay) * $echelle));

        imagecopyresampled(
            $montage, $overlay,
            (int) round($calque['x'] * $largeur - $cible / 2),
            (int) round($calque['y'] * $hauteur - $hauteurCible / 2),
            0, 0,
            $cible, $hauteurCible,
            imagesx($overlay), imagesy($overlay)
        );
        imagedestroy($overlay);
    }

    private function borne(float $valeur, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $valeur));
    }
}
