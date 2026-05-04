<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';

class OAuthController
{
    private static function clientId(): string
    {
        return getenv('FT_CLIENT_ID') ?: '';
    }

    private static function clientSecret(): string
    {
        return getenv('FT_CLIENT_SECRET') ?: '';
    }

    private static function redirectUri(): string
    {
        return getenv('FT_REDIRECT_URI') ?: 'https://localhost:8443/api/auth/oauth/callback';
    }

    private static function frontendUrl(): string
    {
        return getenv('FRONTEND_URL') ?: 'https://localhost:8443';
    }

    // GET /oauth/start  →  redirect to 42 authorization page
    public static function start(): void
    {
        if (!self::clientId()) {
            http_response_code(503);
            echo json_encode(['error' => '42 OAuth not configured.']);
            return;
        }

        $state = bin2hex(random_bytes(16));
        setcookie('oauth_state', $state, [
            'expires'  => time() + 600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => true,
        ]);

        $url = 'https://api.intra.42.fr/oauth/authorize?' . http_build_query([
            'client_id'     => self::clientId(),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'state'         => $state,
        ]);

        header('Location: ' . $url);
        http_response_code(302);
    }

    // GET /oauth/callback?code=&state=  →  exchange code, find/create user, redirect with JWT
    public static function callback(): void
    {
        $frontend = self::frontendUrl();

        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';

        // Validate CSRF state
        $expected = $_COOKIE['oauth_state'] ?? '';
        setcookie('oauth_state', '', ['expires' => time() - 1, 'path' => '/']);

        if (!$code || !$state || !$expected || !hash_equals($expected, $state)) {
            header('Location: ' . $frontend . '/login?error=oauth_invalid_state');
            http_response_code(302);
            return;
        }

        // Exchange code for access token
        $tokenData = self::exchangeCode($code);
        if (!$tokenData || empty($tokenData['access_token'])) {
            header('Location: ' . $frontend . '/login?error=oauth_token_failed');
            http_response_code(302);
            return;
        }

        // Fetch user info from 42 API
        $ftUser = self::fetchFtUser($tokenData['access_token']);
        if (!$ftUser || empty($ftUser['id'])) {
            header('Location: ' . $frontend . '/login?error=oauth_userinfo_failed');
            http_response_code(302);
            return;
        }

        // Find or create local user
        try {
            $user = self::findOrCreateUser($ftUser);
        } catch (\Exception) {
            header('Location: ' . $frontend . '/login?error=oauth_db_error');
            http_response_code(302);
            return;
        }

        $jwt = JWT::encode([
            'sub'      => $user['id'],
            'username' => $user['username'],
        ]);

        // Redirect to frontend root with token as query param; router.js picks it up
        header('Location: ' . $frontend . '/?token=' . urlencode($jwt));
        http_response_code(302);
    }

    private static function exchangeCode(string $code): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query([
                    'grant_type'    => 'authorization_code',
                    'client_id'     => self::clientId(),
                    'client_secret' => self::clientSecret(),
                    'code'          => $code,
                    'redirect_uri'  => self::redirectUri(),
                ]),
                'timeout'          => 10,
                'ignore_errors'    => true,
            ],
        ]);
        $resp = @file_get_contents('https://api.intra.42.fr/oauth/token', false, $ctx);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    private static function fetchFtUser(string $accessToken): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'       => 'GET',
                'header'       => 'Authorization: Bearer ' . $accessToken . "\r\n",
                'timeout'      => 10,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents('https://api.intra.42.fr/v2/me', false, $ctx);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    private static function findOrCreateUser(array $ftUser): array
    {
        $db   = Database::get();
        $ftId = (int) $ftUser['id'];

        // Look up existing user by ft_id
        $stmt = $db->prepare('SELECT * FROM users WHERE ft_id = :fid');
        $stmt->execute(['fid' => $ftId]);
        $user = $stmt->fetch();
        if ($user) return $user;

        // Build a unique username from the 42 login
        $base     = preg_replace('/[^a-zA-Z0-9_]/', '_', $ftUser['login'] ?? 'user42');
        $username = $base;
        $stmt     = $db->prepare('SELECT id FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        if ($stmt->fetch()) {
            $username = $base . '_42';
        }

        $email = filter_var($ftUser['email'] ?? '', FILTER_VALIDATE_EMAIL)
            ? $ftUser['email']
            : $username . '@42.fr';

        // Insert verified OAuth user (no usable password)
        $db->prepare(
            'INSERT INTO users (username, email, password_hash, verified, ft_id)
             VALUES (:u, :e, :h, 1, :fid)'
        )->execute([
            'u'   => $username,
            'e'   => $email,
            'h'   => '',    // cannot log in with password
            'fid' => $ftId,
        ]);

        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => (int) $db->lastInsertId()]);
        return $stmt->fetch();
    }
}
