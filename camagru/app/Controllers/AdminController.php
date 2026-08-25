<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Pg;
use App\Models\Image;
use App\Models\Report;
use App\Models\User;
use App\Services\Montage;

final class AdminController extends Controller
{
    public function admin(): void
    {
        $this->view('admin', [
            'title'   => 'Admin Panel',
            'signals' => (new Report())->pending(),
            'comptes' => (new User())->all(),
            'moi'     => $this->viewerId(),
        ] + Flash::pull());
    }

    /** Clears the reports and keeps the montage: nothing was wrong with it. */
    public function dismiss(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && (new Report())->dismiss($id)) {
            Flash::notice('Reports cleared.');
        } else {
            Flash::errors(['No report on this montage.']);
        }

        $this->redirect('/admin');
    }

    public function deleteMontage(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $filename = $id > 0 ? (new Image())->remove($id) : null;

        if ($filename === null) {
            Flash::errors(['This montage no longer exists.']);
        } else {
            (new Montage())->remove($filename);
            Flash::notice('Montage deleted.');
        }

        $this->redirect('/admin');
    }

    /** Suspending keeps the account and its montages; only the login is closed. */
    public function suspend(): void
    {
        $id    = (int) ($_POST['id'] ?? 0);
        $users = new User();
        $cible = $id > 0 ? $users->findById($id) : null;

        if ($cible === null) {
            Flash::errors(['Unknown account.']);
            $this->redirect('/admin');
        }

        if ($id === $this->viewerId()) {
            Flash::errors(['You cannot suspend your own account.']);
            $this->redirect('/admin');
        }

        if ($users->isAdmin($id)) {
            Flash::errors(['An admin account cannot be suspended.']);
            $this->redirect('/admin');
        }

        $suspendu = !Pg::bool($cible['suspended'] ?? null);
        $users->setSuspended($id, $suspendu);

        Flash::notice($cible['username'] . ($suspendu ? ' is suspended.' : ' can log in again.'));
        $this->redirect('/admin');
    }

    private function viewerId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}
