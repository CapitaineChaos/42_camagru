<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Mailer.php';

class AuthController
{
    // POST /register  { username, email, password }
    public static function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $username = trim($input['username'] ?? '');
        $email    = trim($input['email']    ?? '');
        $password = $input['password']      ?? '';

        // --- Validation ---
        $errors = [];

        if (strlen($username) < 3 || strlen($username) > 30) {
            $errors[] = 'Le nom d\'utilisateur doit faire entre 3 et 30 caractères.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que lettres, chiffres et _.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        }
        if ($errors) {
            http_response_code(422);
            echo json_encode(['error' => implode(' ', $errors)]);
            return;
        }

        // --- Uniqueness check ---
        $db = Database::get();

        $stmt = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
        $stmt->execute(['u' => $username, 'e' => $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Ce nom d\'utilisateur ou cet email est déjà pris.']);
            return;
        }

        // --- Insert ---
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :h)'
        );
        $stmt->execute(['u' => $username, 'e' => $email, 'h' => $hash]);
        $userId = (int) $db->lastInsertId();

        // --- Send verification email ---
        $vtoken  = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $db->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:uid, :tok, :exp)')
           ->execute(['uid' => $userId, 'tok' => $vtoken, 'exp' => $expires]);

        try {
            Mailer::sendVerification($email, $vtoken);
        } catch (RuntimeException) {
            // Mail failed but account is created — user can request a new link later
        }

        http_response_code(201);
        echo json_encode(['message' => 'Compte créé. Vérifiez votre email pour activer votre compte.']);
    }

    // POST /login  { username, password }
    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $username = trim($input['username'] ?? '');
        $password = $input['password']      ?? '';

        if (!$username || !$password) {
            http_response_code(422);
            echo json_encode(['error' => 'Champs requis manquants.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Identifiants incorrects.']);
            return;
        }

        if (!$user['verified']) {
            http_response_code(403);
            echo json_encode(['error' => 'Compte non vérifié. Consultez vos emails.']);
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

    // GET /me  (requires Bearer token)
    public static function me(): void
    {
        $payload = JWT::auth();
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $payload['sub']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Utilisateur introuvable.']);
            return;
        }

        echo json_encode(['user' => $user]);
    }

    // POST /forgot  { email }
    public static function forgot(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($input['email'] ?? '');

        // Always respond the same way to avoid email enumeration
        $generic = ['message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'];

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

        // Delete any existing reset token for this user
        $db->prepare('DELETE FROM password_resets WHERE user_id = :id')
           ->execute(['id' => $user['id']]);

        $token    = bin2hex(random_bytes(32));
        $expires  = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :tok, :exp)')
           ->execute(['uid' => $user['id'], 'tok' => $token, 'exp' => $expires]);

        try {
            Mailer::sendReset($email, $token);
        } catch (RuntimeException) {
            http_response_code(503);
            echo json_encode(['error' => 'Serveur d\'email inaccessible.']);
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
            echo json_encode(['error' => 'Token manquant ou mot de passe trop court (8 caractères min).']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM password_resets WHERE token = :tok');
        $stmt->execute(['tok' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            http_response_code(400);
            echo json_encode(['error' => 'Lien invalide ou déjà utilisé.']);
            return;
        }

        if (strtotime($reset['expires_at']) < time()) {
            $db->prepare('DELETE FROM password_resets WHERE token = :tok')
               ->execute(['tok' => $token]);
            http_response_code(400);
            echo json_encode(['error' => 'Ce lien a expiré. Faites une nouvelle demande.']);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
           ->execute(['h' => $hash, 'id' => $reset['user_id']]);

        $db->prepare('DELETE FROM password_resets WHERE token = :tok')
           ->execute(['tok' => $token]);

        echo json_encode(['message' => 'Mot de passe mis à jour. Vous pouvez vous connecter.']);
    }

    // GET /verify?token=xxx
    public static function verify(): void
    {
        $token = trim($_GET['token'] ?? '');

        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token manquant.']);
            return;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM email_verifications WHERE token = :tok');
        $stmt->execute(['tok' => $token]);
        $row  = $stmt->fetch();

        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'Lien invalide ou déjà utilisé.']);
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            $db->prepare('DELETE FROM email_verifications WHERE token = :tok')
               ->execute(['tok' => $token]);
            http_response_code(400);
            echo json_encode(['error' => 'Ce lien a expiré. Créez un nouveau compte ou contactez le support.']);
            return;
        }

        $db->prepare('UPDATE users SET verified = 1 WHERE id = :id')
           ->execute(['id' => $row['user_id']]);

        $db->prepare('DELETE FROM email_verifications WHERE token = :tok')
           ->execute(['tok' => $token]);

        echo json_encode(['message' => 'Compte vérifié ! Vous pouvez vous connecter.']);
    }
}
