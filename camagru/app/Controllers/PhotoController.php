<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Models\Image;
use App\Services\Montage;

/** Serves the montages: storage/ sits outside the DocumentRoot. */
final class PhotoController extends Controller
{
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $image = $id > 0 ? (new Image())->findById($id) : null;
        $fichier = $image !== null ? (new Montage())->path((string) $image['filename']) : null;

        if ($fichier === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($fichier));
        // the id addresses one immutable file: an edit creates another montage
        header('Cache-Control: public, max-age=604800, immutable');
        readfile($fichier);
    }

    /** Removes the montage, its likes and its comments, then the file itself. */
    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $filename = $id > 0 ? (new Image())->delete($id, $userId) : null;

        if ($filename === null) {
            Flash::errors(['This montage is not yours, or no longer exists.']);
        } else {
            (new Montage())->remove($filename);
            Flash::notice('Montage deleted.');
        }

        $this->redirect($this->retour());
    }

    private function retour(): string
    {
        if (($_POST['back'] ?? '') !== 'gallery') {
            return '/photobooth';
        }

        return '/gallery?page=' . max(1, (int) ($_POST['page'] ?? 1));
    }
}
