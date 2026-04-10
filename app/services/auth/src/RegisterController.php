<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class RegisterController
{
    // POST /register  { username, email, password }
    public static function handle(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $username = trim($input['username'] ?? '');
        $email    = trim($input['email']    ?? '');
        $password = $input['password']      ?? '';

        $errors = [];

        if (strlen($username) < 3 || strlen($username) > 30) {
            $errors[] = 'Username must be between 3 and 30 characters.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, digits and _.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            http_response_code(422);
            echo json_encode(['error' => implode(' ', $errors)]);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
        $stmt->execute(['u' => $username, 'e' => $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'This username or email is already taken.']);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :h)')
           ->execute(['u' => $username, 'e' => $email, 'h' => $hash]);
        $userId = (int) $db->lastInsertId();

        $vtoken  = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $db->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:uid, :tok, :exp)')
           ->execute(['uid' => $userId, 'tok' => $vtoken, 'exp' => $expires]);

        try {
            Mailer::sendVerification($email, $vtoken);
        } catch (RuntimeException) {}

        http_response_code(201);
        echo json_encode(['message' => 'Account created. Check your email to activate your account.']);
    }
}
