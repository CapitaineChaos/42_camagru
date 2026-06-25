<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
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

    public function create(string $username, string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password)
             VALUES (:username, :email, :password)
             RETURNING id'
        );
        $stmt->execute([
            'username' => $username,
            'email'    => $email,
            'password' => $passwordHash,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
