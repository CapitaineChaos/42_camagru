<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Settings;
use App\Models\Comment;
use App\Models\Image;
use App\Models\Like;

final class GalleryController extends Controller
{
    public function gallery(): void
    {
        $images  = new Image();
        $parPage = max(1, (int) Settings::get('gallery.per_page', 6));
        $total   = $images->count();
        $pages   = max(1, (int) ceil($total / $parPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $liste = $images->page($parPage, ($page - 1) * $parPage, $this->viewerId());

        $this->view('gallery', [
            'title'        => 'Gallery',
            'images'       => $liste,
            'commentaires' => (new Comment())->forImages(
                array_map(static fn (array $image): int => (int) $image['id'], $liste)
            ),
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'viewerId'     => $this->viewerId(),
            'maxComment'   => (int) Settings::get('comments.max_length', 500),
        ] + Flash::pull());
    }

    public function like(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($this->cible($id) !== null) {
            (new Like())->toggle($id, (int) $_SESSION['user']['id']);
        }

        $this->redirect($this->retour($id));
    }

    public function comment(): void
    {
        $id      = (int) ($_POST['id'] ?? 0);
        $texte   = trim((string) ($_POST['comment'] ?? ''));
        $maximum = (int) Settings::get('comments.max_length', 500);

        if ($this->cible($id) === null) {
            $this->redirect($this->retour($id));
        }

        if ($texte === '') {
            Flash::errors(['Empty comment.']);
        } elseif (mb_strlen($texte) > $maximum) {
            Flash::errors(['Comment too long: ' . $maximum . ' characters at most.']);
        } else {
            (new Comment())->create($id, (int) $_SESSION['user']['id'], $texte);
        }

        $this->redirect($this->retour($id));
    }

    /**
     * The montage a reader may like or comment: someone else's, and still there.
     *
     * @return array<string, mixed>|null null once the refusal is flashed
     */
    private function cible(int $id): ?array
    {
        $image = $id > 0 ? (new Image())->findById($id) : null;

        if ($image === null) {
            Flash::errors(['This montage no longer exists.']);
            return null;
        }

        if ((int) $image['user_id'] === $this->viewerId()) {
            Flash::errors(['You cannot like or comment your own montage.']);
            return null;
        }

        return $image;
    }

    private function retour(int $id): string
    {
        $page = max(1, (int) ($_POST['page'] ?? 1));

        return '/gallery?page=' . $page . ($id > 0 ? '#montage-' . $id : '');
    }

    private function viewerId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }
}
