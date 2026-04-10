<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Mailer.php';

class ProfileController
{
    // GET /me
    public static function get(): void
    {
        $payload = JWT::auth();
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $payload['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found.']);
            return;
        }

        echo json_encode(['user' => $user]);
    }

    // PATCH /me  { username?, email?, current_password?, new_password? }
    public static function update(): void
    {
        $payload = JWT::auth();
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $db    = Database::get();

        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $payload['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found.']);
            return;
        }

        $errors = [];

        $newUsername = isset($input['username']) ? trim($input['username']) : null;
        if ($newUsername !== null) {
            if (strlen($newUsername) < 3 || strlen($newUsername) > 30) {
                $errors[] = 'Username must be between 3 and 30 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                $errors[] = 'Username can only contain letters, digits and _.';
            } else {
                $check = $db->prepare('SELECT id FROM users WHERE username = :u AND id != :id');
                $check->execute(['u' => $newUsername, 'id' => $user['id']]);
                if ($check->fetch()) {
                    $errors[] = 'This username is already taken.';
                }
            }
        }

        $newEmail = isset($input['email']) ? trim($input['email']) : null;
        if ($newEmail !== null) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            } else {
                $check = $db->prepare('SELECT id FROM users WHERE email = :e AND id != :id');
                $check->execute(['e' => $newEmail, 'id' => $user['id']]);
                if ($check->fetch()) {
                    $errors[] = 'This email is already in use.';
                }
            }
        }

        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password']     ?? '';
        $changePassword  = $currentPassword !== '' || $newPassword !== '';

        if ($changePassword) {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
        }

        if ($errors) {
            http_response_code(422);
            echo json_encode(['error' => implode(' ', $errors)]);
            return;
        }

        if ($newUsername !== null) {
            $db->prepare('UPDATE users SET username = :u WHERE id = :id')
               ->execute(['u' => $newUsername, 'id' => $user['id']]);
        }

        $emailChangePending = false;
        if ($newEmail !== null && $newEmail !== $user['email']) {
            // Do NOT update users.email or users.verified yet:
            // the old email stays active until the new one is confirmed.
            $db->prepare('DELETE FROM email_verifications WHERE user_id = :id')
               ->execute(['id' => $user['id']]);
            $vtoken  = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 86400);
            $db->prepare(
                'INSERT INTO email_verifications (user_id, token, expires_at, pending_email)
                 VALUES (:uid, :tok, :exp, :pe)'
            )->execute(['uid' => $user['id'], 'tok' => $vtoken, 'exp' => $expires, 'pe' => $newEmail]);
            try {
                Mailer::sendEmailChange($newEmail, $vtoken);
            } catch (RuntimeException) {}
            $emailChangePending = true;
        }

        if ($changePassword) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
               ->execute(['h' => $hash, 'id' => $user['id']]);
        }

        $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $updated = $stmt->fetch();

        $message = $emailChangePending
            ? "A confirmation email has been sent to $newEmail. Your current address remains active until validated."
            : 'Profile updated.';

        echo json_encode(['user' => $updated, 'message' => $message]);
    }
}
