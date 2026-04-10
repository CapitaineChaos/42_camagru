<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class PasswordController
{
    // POST /forgot  { email }
    public static function forgot(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($input['email'] ?? '');

        // Generic response to avoid email enumeration
        $generic = ['message' => 'If this email exists, a reset link has been sent.'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode($generic);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode($generic);
            return;
        }

        $db->prepare('DELETE FROM password_resets WHERE user_id = :id')
           ->execute(['id' => $user['id']]);

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :tok, :exp)')
           ->execute(['uid' => $user['id'], 'tok' => $token, 'exp' => $expires]);

        try {
            Mailer::sendReset($email, $token);
        } catch (RuntimeException) {
            http_response_code(503);
            echo json_encode(['error' => 'Email server unavailable.']);
            return;
        }

        echo json_encode($generic);
    }

    // POST /reset  { token, password }
    public static function reset(): void
    {
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $token    = trim($input['token']    ?? '');
        $password = $input['password']      ?? '';

        if (!$token || strlen($password) < 8) {
            http_response_code(422);
            echo json_encode(['error' => 'Missing token or password too short (8 characters minimum).']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM password_resets WHERE token = :tok');
        $stmt->execute(['tok' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or already used link.']);
            return;
        }

        if (strtotime($reset['expires_at']) < time()) {
            $db->prepare('DELETE FROM password_resets WHERE token = :tok')
               ->execute(['tok' => $token]);
            http_response_code(400);
            echo json_encode(['error' => 'This link has expired. Please make a new request.']);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
           ->execute(['h' => $hash, 'id' => $reset['user_id']]);

        $db->prepare('DELETE FROM password_resets WHERE token = :tok')
           ->execute(['tok' => $token]);

        echo json_encode(['message' => 'Password updated. You can now log in.']);
    }
}
