<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CurrentUser;
use App\Services\Notifications;

final class FriendsController extends Controller
{
    public function friends(): void
    {
        $moi = $this->viewerId();
        $relations = (new Friendship())->all($moi);

        $groupes = [Friendship::INCOMING => [], Friendship::FRIEND => [], Friendship::OUTGOING => []];
        foreach ($relations as $relation) {
            $groupes[(string) $relation['state']][] = $relation;
        }

        $recherche = trim((string) ($_GET['q'] ?? ''));

        $this->view('friends', [
            'title'     => 'Friends',
            'incoming'  => $this->withAvatars($groupes[Friendship::INCOMING]),
            'friends'   => $this->withAvatars($groupes[Friendship::FRIEND]),
            'outgoing'  => $this->withAvatars($groupes[Friendship::OUTGOING]),
            'recherche' => $recherche,
            'resultats' => $recherche === ''
                ? []
                : $this->withAvatars((new User())->search($recherche, $moi)),
            'etats'     => array_column($relations, 'state', 'id'),
        ] + Flash::pull());
    }

    public function request(): void
    {
        $autre = $this->autre();

        if ($autre !== null) {
            $nom = (string) $autre['username'];

            match ((new Friendship())->request($this->viewerId(), (int) $autre['id'])) {
                Friendship::SENT     => $this->sent($nom, (int) $autre['id']),
                Friendship::ACCEPTED => $this->accepted($nom, (int) $autre['id']),
                Friendship::FRIENDS  => Flash::errors(['You are already friends with ' . $nom . '.']),
                Friendship::SELF     => Flash::errors(['You cannot add yourself.']),
                default              => Flash::errors(['A request between you and ' . $nom . ' is already waiting.']),
            };
        }

        $this->redirect('/friends');
    }

    public function accept(): void
    {
        $autre = $this->autre();

        if ($autre !== null) {
            if ((new Friendship())->accept($this->viewerId(), (int) $autre['id'])) {
                $this->accepted((string) $autre['username'], (int) $autre['id']);
            } else {
                Flash::errors(['No request from ' . $autre['username'] . ' is waiting.']);
            }
        }

        $this->redirect('/friends');
    }

    public function remove(): void
    {
        $autre = $this->autre();

        if ($autre !== null) {
            $nom = (string) $autre['username'];

            match ((new Friendship())->remove($this->viewerId(), (int) $autre['id'])) {
                Friendship::FRIEND   => $this->unfriended($nom, (int) $autre['id']),
                Friendship::INCOMING => Flash::notice('Request from ' . $nom . ' declined.'),
                Friendship::OUTGOING => Flash::notice('Request to ' . $nom . ' cancelled.'),
                default              => Flash::errors(['Nothing links you to ' . $nom . '.']),
            };
        }

        $this->redirect('/friends');
    }

    private function sent(string $nom, int $addresseeId): void
    {
        Flash::notice('Request sent to ' . $nom . '.');
        (new Notifications())->friendRequest($addresseeId, (string) $_SESSION['user']['username']);
    }

    /** Both ways in: answering a pending request, and asking back someone who asked first. */
    private function accepted(string $nom, int $demandeurId): void
    {
        Flash::notice('You and ' . $nom . ' are now friends.');
        (new Notifications())->friendAccepted($demandeurId, (string) $_SESSION['user']['username']);
    }

    private function unfriended(string $nom, int $autreId): void
    {
        Flash::notice($nom . ' is no longer in your friends.');
        (new Notifications())->friendRemoved($autreId, (string) $_SESSION['user']['username']);
    }

    /**
     * The account an action names.
     *
     * @return array<string, mixed>|null null once the refusal is flashed
     */
    private function autre(): ?array
    {
        $id = (int) ($_POST['user'] ?? 0);
        $autre = $id > 0 ? (new User())->findById($id) : null;

        if ($autre === null) {
            Flash::errors(['Unknown account.']);
        }

        return $autre;
    }

    /**
     * @param list<array<string, mixed>> $utilisateurs
     * @return list<array<string, mixed>>
     */
    private function withAvatars(array $utilisateurs): array
    {
        $comptes = new CurrentUser();

        return array_map(
            static fn (array $utilisateur): array
                => $utilisateur + ['avatar_url' => $comptes->avatarUrl($utilisateur)],
            $utilisateurs
        );
    }

    private function viewerId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}
