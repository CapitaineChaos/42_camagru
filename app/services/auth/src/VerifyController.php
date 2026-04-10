<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class VerifyController
{
    // GET /verify?token=xxx
    public static function handle(): void
    {
        $token = trim($_GET['token'] ?? '');

        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing token.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM email_verifications WHERE token = :tok');
        $stmt->execute(['tok' => $token]);
        $row  = $stmt->fetch();

        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or already used link.']);
            return;
        }

        $isEmailChange = !empty($row['pending_email']);

        if (strtotime($row['expires_at']) < time()) {
            $db->prepare('DELETE FROM email_verifications WHERE token = :tok')
               ->execute(['tok' => $token]);
            http_response_code(400);
            $expiredMsg = $isEmailChange
                ? 'This link has expired. Go back to your profile to request a new email change link.'
                : 'This link has expired. Please create a new account or contact support.';
            echo json_encode(['error' => $expiredMsg]);
            return;
        }

        if ($isEmailChange) {
            // Email change: update the email and keep verified = 1
            $db->prepare('UPDATE users SET email = :e WHERE id = :id')
               ->execute(['e' => $row['pending_email'], 'id' => $row['user_id']]);
            $db->prepare('DELETE FROM email_verifications WHERE token = :tok')
               ->execute(['tok' => $token]);
            echo json_encode(['message' => 'Your email address has been updated.']);
        } else {
            // Account creation: verify
            $db->prepare('UPDATE users SET verified = 1 WHERE id = :id')
               ->execute(['id' => $row['user_id']]);
            $db->prepare('DELETE FROM email_verifications WHERE token = :tok')
               ->execute(['tok' => $token]);
            echo json_encode(['message' => 'Account verified! You can now log in.']);
        }
    }
}
