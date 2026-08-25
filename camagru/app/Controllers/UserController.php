<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Models\Image;
use App\Models\User;
use App\Services\Avatars;
use App\Services\CurrentUser;
use App\Services\Montage;
use RuntimeException;

final class UserController extends Controller
{
    public function profile(): void
    {
        $comptes = new CurrentUser();
        $user    = $comptes->fromSession($_SESSION);

        $this->view('profile', [
            'title'         => 'Profile',
            'modeles'       => (new Avatars())->models(),
            'montages'      => (new Image())->forUser($this->viewerId()),
            'avatarCourant' => $user !== null ? $comptes->avatarFilename($user) : '',
            'avatarModele'  => $user !== null && $comptes->usesModelAvatar($user),
        ] + Flash::pull());
    }

    /** Takes the new avatar from the models on offer, or from one of the reader's montages. */
    public function avatar(): void
    {
        $modele  = (string) ($_POST['model'] ?? '');
        $imageId = (int) ($_POST['montage'] ?? 0);

        if ($modele !== '' && (new Avatars())->isModel($modele)) {
            $this->replace($modele, true);
            Flash::notice('Avatar updated.');
        } elseif ($imageId > 0) {
            $this->fromMontage($imageId);
        } else {
            Flash::errors(['Unknown avatar.']);
        }

        $this->redirect('/profile');
    }

    private function fromMontage(int $imageId): void
    {
        $image = (new Image())->findById($imageId);

        if ($image === null || (int) $image['user_id'] !== $this->viewerId()) {
            Flash::errors(['This montage is not yours, or no longer exists.']);
            return;
        }

        $fichier = (new Montage())->path((string) $image['filename']);
        if ($fichier === null) {
            Flash::errors(['This montage is no longer stored.']);
            return;
        }

        try {
            $this->replace((new Avatars())->fromMontage($fichier), false);
            Flash::notice('Avatar updated.');
        } catch (RuntimeException $e) {
            error_log('Avatar rejected: ' . $e->getMessage());
            Flash::errors([$e->getMessage()]);
        }
    }

    /** Writes the new avatar, then drops the file the old one left behind. */
    private function replace(string $avatar, bool $modele): void
    {
        $users  = new User();
        $userId = $this->viewerId();
        $ancien = (string) (($users->findById($userId))['avatar'] ?? '');

        $users->updateAvatar($userId, $avatar, $modele);
        (new Avatars())->discard($ancien);
    }

    private function viewerId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}
