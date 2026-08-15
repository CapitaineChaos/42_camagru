<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Settings;
use App\Models\Image;
use App\Services\Montage;
use App\Services\Overlays;
use RuntimeException;
use Throwable;

final class PhotoboothController extends Controller
{
    public function photobooth(): void
    {
        $this->view('photobooth', [
            'title'    => 'Photobooth',
            'overlays' => (new Overlays())->catalogue(),
            'montages' => (new Image())->forUser($this->userId()),
            'largeur'  => (int) Settings::get('photobooth.width', 800),
            'hauteur'  => (int) Settings::get('photobooth.height', 600),
        ] + Flash::pull());
    }

    public function capture(): void
    {
        $montage = new Montage();

        try {
            $calques = $montage->layers((string) ($_POST['layers'] ?? ''));
            $source  = $this->source($montage);

            $nom = $montage->compose($source, $calques);
            (new Image())->create($this->userId(), $nom);

            Flash::notice('Montage saved.');
        } catch (RuntimeException $e) {
            error_log('Montage rejected: ' . $e->getMessage());
            Flash::errors([$e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Montage failed: ' . $e->getMessage());
            Flash::errors(['Montage failed.']);
        }

        $this->redirect('/photobooth');
    }

    /** @throws RuntimeException */
    private function source(Montage $montage): \GdImage
    {
        $fichier = $_FILES['file'] ?? null;
        if (is_array($fichier) && (int) $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
            return $montage->fromUpload($fichier);
        }

        $capture = (string) ($_POST['capture'] ?? '');
        if ($capture === '') {
            throw new RuntimeException('Take a shot or pick an image first.');
        }

        return $montage->fromDataUrl($capture);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }
}
