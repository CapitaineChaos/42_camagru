<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Pg;
use App\Core\Settings;
use App\Core\Text;
use App\Models\User;
use App\Services\Avatars;
use App\Services\Montage;
use App\Services\Notifications;

final class PrefsController extends Controller
{
    public function prefs(): void
    {
        $user = $this->user();

        $reglages = [];
        foreach (Notifications::COLONNES as $colonne) {
            $reglages[$colonne] = Pg::bool($user[$colonne] ?? null);
        }

        $this->view('preferences', [
            'title'    => 'Preferences',
            'compte'   => ['username' => $user['username'], 'email' => $user['email']],
            'reglages' => $reglages,
        ] + Flash::pull());
    }

    /** Username, email address and password: the current password gates all three. */
    public function account(): void
    {
        $user     = $this->user();
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $nouveau  = (string) ($_POST['password'] ?? '');
        $minimum  = (int) Settings::get('auth.password_min_length', 8);

        $users  = new User();
        $errors = [];

        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $user['password'])) {
            $errors[] = 'Current password is wrong.';
        }
        if ($username === '' || $email === '') {
            $errors[] = 'Username and email address are required.';
        }
        if (mb_strlen($username) > 50) {
            $errors[] = 'Username is limited to 50 characters.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if ($this->pris($users->findByUsername($username))) {
            $errors[] = 'This username is already taken.';
        }
        if ($this->pris($users->findByEmail($email))) {
            $errors[] = 'This email address is already taken.';
        }
        if ($nouveau !== '' && strlen($nouveau) < $minimum) {
            $errors[] = 'Password must be at least ' . $minimum . ' characters long.';
        }

        if ($errors !== []) {
            Flash::errors($errors);
            $this->redirect('/preferences');
        }

        $users->updateIdentity($this->viewerId(), $username, $email);
        if ($nouveau !== '') {
            $users->updatePassword($this->viewerId(), password_hash($nouveau, PASSWORD_DEFAULT));
        }

        // the session carries the username, and CurrentUser reads the account by it
        $_SESSION['user']['username'] = $username;

        Flash::notice('Account updated.');
        $this->redirect('/preferences');
    }

    /** Irreversible: the password and an explicit tick are both required. */
    public function deleteAccount(): void
    {
        $user = $this->user();

        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $user['password'])) {
            Flash::errors(['Current password is wrong.']);
            $this->redirect('/preferences');
        }

        if (!isset($_POST['confirm'])) {
            Flash::errors(['Tick the box to confirm the deletion.']);
            $this->redirect('/preferences');
        }

        $email    = (string) $user['email'];
        $username = (string) $user['username'];
        $avatar   = (string) ($user['avatar'] ?? '');

        $montages = (new User())->delete($this->viewerId());

        $fichiers = new Montage();
        foreach ($montages as $filename) {
            $fichiers->remove($filename);
        }
        (new Avatars())->discard($avatar);

        $this->farewell($email, $username, count($montages));

        // a fresh empty session, so the notice survives the account it belonged to
        $_SESSION = [];
        session_regenerate_id(true);
        Flash::notice('Account deleted. A confirmation has been sent to ' . $email . '.');
        $this->redirect('/login');
    }

    private function farewell(string $email, string $username, int $montages): void
    {
        Mailer::sendOrLog(
            $email,
            'Your Camagru account has been deleted',
            'Hi ' . htmlspecialchars($username) . ',<br><br>'
            . 'Your account is gone, along with '
            . Text::plural($montages, 'montage') . '.<br>'
            . 'The comments you left on other montages stay there, without your name.'
        );
    }

    public function notifications(): void
    {
        $reglages = [];
        foreach (Notifications::COLONNES as $colonne) {
            $reglages[$colonne] = isset($_POST[$colonne]);
        }

        (new User())->updateNotifications($this->viewerId(), $reglages);

        Flash::notice('Notifications updated.');
        $this->redirect('/preferences');
    }

    /** @param array<string, mixed>|null $trouve */
    private function pris(?array $trouve): bool
    {
        return $trouve !== null && (int) $trouve['id'] !== $this->viewerId();
    }

    /** @return array<string, mixed> */
    private function user(): array
    {
        $user = (new User())->findById($this->viewerId());

        if ($user === null) {
            $this->redirect('/');
        }

        return $user;
    }

    private function viewerId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}
