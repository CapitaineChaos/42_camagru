<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Models\User;
use Throwable;

final class AuthController extends Controller
{
    public function showRegister(): void
    {
        $this->view('auth/register', ['title' => 'Inscription']);
    }

    public function register(): void
    {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($username === '' || $email === '' || $password === '') {
            $errors[] = 'Tous les champs sont requis.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        }

        $users = new User();
        if ($errors === [] && ($users->findByEmail($email) || $users->findByUsername($username))) {
            $errors[] = 'Un compte existe déjà avec cet email ou ce pseudo.';
        }

        if ($errors !== []) {
            $this->view('auth/register', [
                'title'  => 'Inscription',
                'errors' => $errors,
                'old'    => ['username' => $username, 'email' => $email],
            ]);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $users->create($username, $email, password_hash($password, PASSWORD_DEFAULT), $token);

        $link = APP_URL . '/verify?token=' . $token;
        try {
            Mailer::send(
                $email,
                'Confirmez votre inscription à Camagru',
                'Bonjour ' . htmlspecialchars($username) . ',<br><br>'
                . 'Cliquez sur ce lien pour activer votre compte&nbsp;:<br>'
                . '<a href="' . $link . '">' . $link . '</a>'
            );
        } catch (Throwable $e) {
            error_log('Envoi email de confirmation échoué : ' . $e->getMessage());
        }

        $this->view('auth/login', [
            'title'  => 'Connexion',
            'notice' => 'Compte créé. Un email de confirmation vous a été envoyé : '
                . 'cliquez sur le lien pour activer votre compte.',
        ]);
    }

    public function verify(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $users = new User();
        $user  = $token !== '' ? $users->findByToken($token) : null;

        if ($user === null) {
            $this->view('auth/login', [
                'title'  => 'Connexion',
                'errors' => ['Lien de confirmation invalide ou déjà utilisé.'],
            ]);
            return;
        }

        $users->markVerified((int) $user['id']);
        $this->view('auth/login', [
            'title'  => 'Connexion',
            'notice' => 'Votre compte est activé. Vous pouvez maintenant vous connecter.',
        ]);
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Connexion']);
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $user = (new User())->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password'])) {
            $this->view('auth/login', [
                'title'  => 'Connexion',
                'errors' => ['Identifiants invalides.'],
                'old'    => ['email' => $email],
            ]);
            return;
        }

        // PDO_pgsql peut renvoyer le booléen sous forme 't'/'f' : on normalise
        if (!in_array($user['verified'], [true, 't', '1', 1], true)) {
            $this->view('auth/login', [
                'title'  => 'Connexion',
                'errors' => ['Compte non vérifié. Consultez votre email pour l\'activer.'],
                'old'    => ['email' => $email],
            ]);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
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
