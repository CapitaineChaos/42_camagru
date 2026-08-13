<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Settings;
use App\Models\PasswordReset;
use App\Models\User;
use Throwable;

final class PasswordController extends Controller
{
    private const CONFIRMATION = 'If an account matches this address, a reset link has been sent.';

    public function showForgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Lost password']);
    }

    public function sendReset(): void
    {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('auth/forgot', [
                'title'  => 'Lost password',
                'errors' => ['Invalid email address.'],
                'old'    => ['email' => $email],
            ]);
            return;
        }

        $user = (new User())->findByEmail($email);
        if ($user !== null) {
            $this->issue((int) $user['id'], $email, (string) $user['username']);
        }

        $this->view('auth/forgot', [
            'title'  => 'Lost password',
            'notice' => self::CONFIRMATION,
        ]);
    }

    public function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');

        if ($this->demand($token) === null) {
            $this->view('auth/forgot', [
                'title'  => 'Lost password',
                'errors' => ['Reset link invalid, expired or already used. Ask for a new one.'],
            ]);
            return;
        }

        $this->view('auth/reset', ['title' => 'New password', 'token' => $token]);
    }

    public function reset(): void
    {
        $token        = (string) ($_POST['token'] ?? '');
        $password     = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $minimum      = (int) Settings::get('auth.password_min_length', 8);

        $demand = $this->demand($token);
        if ($demand === null) {
            $this->view('auth/forgot', [
                'title'  => 'Lost password',
                'errors' => ['Reset link invalid, expired or already used. Ask for a new one.'],
            ]);
            return;
        }

        $errors = [];
        if (strlen($password) < $minimum) {
            $errors[] = 'Password must be at least ' . $minimum . ' characters long.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'Both passwords must match.';
        }

        if ($errors !== []) {
            $this->view('auth/reset', [
                'title'  => 'New password',
                'errors' => $errors,
                'token'  => $token,
            ]);
            return;
        }

        $resets = new PasswordReset();
        (new User())->updatePassword(
            (int) $demand['user_id'],
            password_hash($password, PASSWORD_DEFAULT)
        );
        $resets->markUsed((int) $demand['id']);

        $this->view('auth/login', [
            'title'  => 'Login',
            'notice' => 'Password updated. You can now log in.',
        ]);
    }

    /** @return array<string, mixed>|null la demande ouverte que porte ce lien */
    private function demand(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return (new PasswordReset())->findValid(hash('sha256', $token));
    }

    private function issue(int $userId, string $email, string $username): void
    {
        $ttl   = (int) Settings::get('auth.password_reset_ttl', 86400);
        $token = bin2hex(random_bytes((int) Settings::get('auth.token_bytes', 32)));

        (new PasswordReset())->create($userId, hash('sha256', $token), $ttl);

        $link = APP_URL . '/reset-password?token=' . $token;
        try {
            Mailer::send(
                $email,
                'Reset your Camagru password',
                'Hi ' . htmlspecialchars($username) . ',<br><br>'
                . 'Click this link to choose a new password:<br>'
                . '<a href="' . $link . '">' . $link . '</a><br><br>'
                . 'The link expires in ' . $this->lifetimeInWords($ttl) . '. '
                . 'If you did not ask for it, ignore this message.'
            );
        } catch (Throwable $e) {
            error_log('Reset email failed: ' . $e->getMessage());
        }
    }
}
