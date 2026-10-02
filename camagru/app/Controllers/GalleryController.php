<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Settings;
use App\Models\Comment;
use App\Models\Image;
use App\Models\Like;
use App\Models\Report;
use App\Services\Notifications;

final class GalleryController extends Controller
{
    public function gallery(): void
    {
        $images  = new Image();
        $parPage = max(1, (int) Settings::get('gallery.per_page'));
        $total   = $images->count();
        $pages   = max(1, (int) ceil($total / $parPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $liste = $images->page($parPage, ($page - 1) * $parPage, $this->viewerId() ?: null);

        $this->view('gallery', [
            'title'        => 'Gallery',
            'images'       => $liste,
            'commentaires' => (new Comment())->forImages(
                array_map(static fn (array $image): int => (int) $image['id'], $liste)
            ),
            'page'         => $page,
            'pages'        => $pages,
            'viewerId'     => $this->viewerId() ?: null,
            'maxComment'   => (int) Settings::get('comments.max_length'),
        ] + Flash::pull());
    }

    public function like(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($this->cible($id) !== null) {
            (new Like())->toggle($id, $this->viewerId());
        }

        $this->redirect($this->retour($id));
    }

    public function report(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($this->cible($id, true) !== null) {
            if ((new Report())->create($id, $this->viewerId())) {
                Flash::notice('Montage reported. An admin will look at it.');
            } else {
                Flash::errors(['You already reported this montage.']);
            }
        }

        $this->redirect($this->retour($id));
    }

    public function comment(): void
    {
        $id      = (int) ($_POST['id'] ?? 0);
        $texte   = trim((string) ($_POST['comment'] ?? ''));
        $maximum = (int) Settings::get('comments.max_length');
        $image   = $this->cible($id);

        if ($image === null) {
            $this->redirect($this->retour($id));
        }

        if ($texte === '') {
            Flash::errors(['Empty comment.']);
        } elseif (mb_strlen($texte) > $maximum) {
            Flash::errors(['Comment too long: ' . $maximum . ' characters at most.']);
        } else {
            (new Comment())->create($id, $this->viewerId(), $texte);
            if ((int) $image['user_id'] !== $this->viewerId()) {
                (new Notifications())->comment(
                    (int) $image['user_id'],
                    (string) $_SESSION['user']['username'],
                    $id
                );
            }
        }

        $this->redirect($this->retour($id));
    }

    /**
     * The montage a reader may act on: still there, and someone else's when
     * $autrui is set (a report on one's own montage makes no sense).
     *
     * @return array<string, mixed>|null null once the refusal is flashed
     */
    private function cible(int $id, bool $autrui = false): ?array
    {
        $image = $id > 0 ? (new Image())->findById($id) : null;

        if ($image === null) {
            Flash::errors(['This montage no longer exists.']);
            return null;
        }

        if ($autrui && (int) $image['user_id'] === $this->viewerId()) {
            Flash::errors(['You cannot report your own montage.']);
            return null;
        }

        return $image;
    }

    private function retour(int $id): string
    {
        $page = max(1, (int) ($_POST['page'] ?? 1));

        return '/gallery?page=' . $page . ($id > 0 ? '#montage-' . $id : '');
    }
}
