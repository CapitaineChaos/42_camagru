<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Email;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Password;
use App\Core\Pg;
use App\Core\Settings;
use App\Core\Username;
use App\Models\User;

final class AuthController extends Controller
{
    public function showRegister(): void
    {
        $this->view('auth/register', ['title' => 'Sign up']);
    }

    public function register(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = Email::normalize((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($username === '' || $email === '' || $password === '') {
            $errors[] = 'All fields are required.';
        }
        $errors = array_merge($errors, Email::errors($email),
                              Username::errors($username), Password::errors($password));

        $users = new User();
        if ($errors === [] && ($users->findByEmail($email) || $users->findByUsername($username))) {
            $errors[] = 'An account already exists with this email or username.';
        }

        if ($errors !== []) {
            $this->view('auth/register', [
                'title'  => 'Sign up',
                'errors' => $errors,
                'old'    => ['username' => $username, 'email' => $email],
            ]);
            return;
        }

        $ttl   = (int) Settings::get('auth.verification_ttl');
        $token = bin2hex(random_bytes((int) Settings::get('auth.token_bytes')));
        $users->create($username, $email, password_hash($password, PASSWORD_DEFAULT), $token, $ttl);

        Mailer::sendOrLog(
            $email,
            $username,
            'Confirm your Camagru account',
            'Click this link to activate your account:<br>'
            . Mailer::link('/verify?token=' . $token) . '<br><br>'
            . 'The link expires in ' . $this->lifetimeInWords($ttl) . '.'
        );

        $this->view('auth/login', [
            'title'  => 'Login',
            'notice' => 'Account created. A confirmation email has been sent: click the link to
                activate your account.',
        ]);
    }

    public function verify(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $users = new User();
        $user  = $token !== '' ? $users->findByToken($token) : null;

        if ($user === null) {
            $this->view('auth/login', [
                'title'  => 'Login',
                'errors' => ['Confirmation link invalid, expired or already used.'],
            ]);
            return;
        }

        $users->markVerified((int) $user['id']);
        $this->welcome((string) $user['email'], (string) $user['username']);

        $this->view('auth/login', [
            'title'  => 'Login',
            'notice' => 'Your account is active. You can now log in.',
        ]);
    }

    /**
     * Sent whatever the notification settings: every confirmed account receives
     * this mail.
     */
    private function welcome(string $email, string $username): void
    {
        Mailer::sendOrLog(
            $email,
            $username,
            'Your Camagru account is active',
            'Your account is confirmed, under the name '
            . '<strong>' . htmlspecialchars($username) . '</strong>:<br>'
            . Mailer::link('/login') . '<br><br>'
            . 'Email notifications are on; they are yours to switch off:<br>'
            . Mailer::link('/preferences')
        );
    }

    /**
     * Availability of a username, for the sign-up form.
     *
     * The answer says nothing that the form does not already say on submit,
     * and nothing about email addresses.
     */
    public function available(): void
    {
        $username = trim((string) ($_GET['username'] ?? ''));
        $errors = Username::errors($username);

        $this->json([
            'username' => $username,
            'valid'    => $errors === [],
            'error'    => $errors[0] ?? null,
            'taken'    => $errors === [] && (new User())->findByUsername($username) !== null,
        ]);
    }

    public function showLogin(): void
    {
        // where a closed session lands: the flash it left is read here
        $this->view('auth/login', ['title' => 'Login'] + Flash::pull());
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $users = new User();
        $user  = $users->findByUsername($username);

        $erreur = match (true) {
            $user === null || !password_verify($password, $user['password'])
                => 'Invalid credentials.',
            !Pg::bool($user['verified'])
                => 'Account not verified. Check your email to activate it.',
            Pg::bool($user['suspended'] ?? null)
                => 'This account is suspended.',
            default => null,
        };

        if ($erreur !== null) {
            $this->view('auth/login', [
                'title'  => 'Login',
                'errors' => [$erreur],
                'old'    => ['username' => $username],
            ]);
            return;
        }

        session_regenerate_id(true);
        $userId = (int) $user['id'];
        $_SESSION['user'] = [
            'id'       => $userId,
            'username' => $user['username'],
            'is_admin' => $users->isAdmin($userId),
        ];
        $this->redirect('/');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect('/');
    }
}
