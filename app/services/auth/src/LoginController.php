<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';

class LoginController
{
    // POST /login  { username, password }
    public static function handle(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $username = trim($input['username'] ?? '');
        $password = $input['password']      ?? '';

        if (!$username || !$password) {
            http_response_code(422);
            echo json_encode(['error' => 'Missing required fields.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials.']);
            return;
        }

        if (!$user['verified']) {
            http_response_code(403);
            echo json_encode(['error' => 'Account not verified. Please check your emails.']);
            return;
        }

        $token = JWT::encode([
            'sub'      => $user['id'],
            'username' => $user['username'],
        ]);

        echo json_encode([
            'token' => $token,
            'user'  => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ],
        ]);
    }
}
