<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PasswordReset extends Model
{
    /** One live link per account. */
    public function create(int $userId, string $tokenHash, int $ttl): void
    {
        $this->deleteForUser($userId);

        $stmt = $this->db->prepare(
            "INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, now() + make_interval(secs => :ttl))"
        );
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'ttl'        => $ttl,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findValid(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > now()'
        );
        $stmt->execute(['token_hash' => $tokenHash]);

        return $stmt->fetch() ?: null;
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE password_resets SET used_at = now() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteForUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
