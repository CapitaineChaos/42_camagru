<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Email;
use App\Core\Mailer;
use App\Core\Password;
use App\Core\Settings;
use App\Models\PasswordReset;
use App\Models\User;

final class PasswordController extends Controller
{
    private const CONFIRMATION = 'If an account matches this address, a reset link has been sent.';

    public function showForgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Lost password']);
    }

    public function sendReset(): void
    {
        $email = Email::normalize((string) ($_POST['email'] ?? ''));

        $errors = Email::errors($email);
        if ($errors !== []) {
            $this->view('auth/forgot', [
                'title'  => 'Lost password',
                'errors' => $errors,
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
            $this->linkExpired();
            return;
        }

        $this->view('auth/reset', ['title' => 'New password', 'token' => $token]);
    }

    public function reset(): void
    {
        $token        = (string) ($_POST['token'] ?? '');
        $password     = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        $demand = $this->demand($token);
        if ($demand === null) {
            $this->linkExpired();
            return;
        }

        $errors = Password::errors($password);
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

    private function linkExpired(): void
    {
        $this->view('auth/forgot', [
            'title'  => 'Lost password',
            'errors' => ['Reset link invalid, expired or already used. Ask for a new one.'],
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
        $ttl   = (int) Settings::get('auth.password_reset_ttl');
        $token = bin2hex(random_bytes((int) Settings::get('auth.token_bytes')));

        (new PasswordReset())->create($userId, hash('sha256', $token), $ttl);

        Mailer::sendOrLog(
            $email,
            $username,
            'Reset your Camagru password',
            'Click this link to choose a new password:<br>'
            . Mailer::link('/reset-password?token=' . $token) . '<br><br>'
            . 'The link expires in ' . $this->lifetimeInWords($ttl) . '. '
            . 'If you did not ask for it, ignore this message.'
        );
    }
}
