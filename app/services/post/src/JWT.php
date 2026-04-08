<?php
declare(strict_types=1);

class JWT
{
    private static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET');
        if (!$secret) {
            throw new RuntimeException('JWT_SECRET not set');
        }
        return $secret;
    }

    public static function encode(array $payload, int $ttl = 86400): string
    {
        $header  = self::base64url(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;
        $body    = self::base64url($payload);
        $sig     = self::sign("$header.$body");
        return "$header.$body.$sig";
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $sig] = $parts;

        if (!hash_equals(self::sign("$header.$body"), $sig)) {
            return null;
        }

        $payload = json_decode(
            base64_decode(strtr($body, '-_', '+/')),
            true
        );

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Extract and verify the Bearer token from the Authorization header.
     * Returns the decoded payload or null.
     */
    public static function auth(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        return self::decode(substr($header, 7));
    }

    private static function base64url(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    private static function sign(string $data): string
    {
        return rtrim(strtr(
            base64_encode(hash_hmac('sha256', $data, self::getSecret(), true)),
            '+/',
            '-_'
        ), '=');
    }
}
