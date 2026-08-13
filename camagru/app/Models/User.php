<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{

    public function isAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);

        return $stmt->fetch() ?: null;
    }

    public function create(
        string $username,
        string $email,
        string $passwordHash,
        string $token,
        int $tokenTtl
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO users (username, email, password, verification_token, verification_expires_at)
             VALUES (:username, :email, :password, :token, now() + make_interval(secs => :ttl))
             RETURNING id"
        );
        $stmt->execute([
            'username' => $username,
            'email'    => $email,
            'password' => $passwordHash,
            'token'    => $token,
            'ttl'      => $tokenTtl,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users
             WHERE verification_token = :token
               AND (verification_expires_at IS NULL OR verification_expires_at > now())'
        );
        $stmt->execute(['token' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['password' => $passwordHash, 'id' => $id]);
    }

    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET verified = TRUE, verification_token = NULL, verification_expires_at = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
