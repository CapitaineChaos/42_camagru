<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

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

        $users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        $this->redirect('/login');
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
