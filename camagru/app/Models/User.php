<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Notifications;
use PDO;
use Throwable;

final class User extends Model
{

    public function isAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * The whole roll for the admin desk, newest accounts last.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->query(
            'SELECT u.id, u.username, u.email, u.created_at, u.suspended,
                    CAST(EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id) AS INTEGER) AS is_admin,
                    (SELECT count(*) FROM images i WHERE i.user_id = u.id) AS montages
             FROM users u
             ORDER BY u.created_at, u.id'
        )->fetchAll();
    }

    public function setSuspended(int $id, bool $suspendu): void
    {
        $stmt = $this->db->prepare('UPDATE users SET suspended = :suspendu WHERE id = :id');
        $stmt->bindValue('suspendu', $suspendu, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
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

    /**
     * Verified accounts whose name contains the term, the searcher aside.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $terme, int $exceptId, int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, avatar, modele FROM users
             WHERE username ILIKE :motif AND id <> :except AND verified
             ORDER BY username
             LIMIT :limite'
        );
        $stmt->bindValue('motif', '%' . addcslashes($terme, '\\%_') . '%');
        $stmt->bindValue('except', $exceptId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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

    public function updateIdentity(int $id, string $username, string $email): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET username = :username, email = :email WHERE id = :id'
        );
        $stmt->execute(['username' => $username, 'email' => $email, 'id' => $id]);
    }

    /**
     * @param array<string, bool> $reglages column name => wanted, unknown names ignored
     */
    public function updateNotifications(int $id, array $reglages): void
    {
        $colonnes = array_intersect_key($reglages, array_flip(Notifications::COLONNES));
        if ($colonnes === []) {
            return;
        }

        $affectations = implode(', ', array_map(
            static fn (string $colonne): string => $colonne . ' = :' . $colonne,
            array_keys($colonnes)
        ));

        $stmt = $this->db->prepare("UPDATE users SET {$affectations} WHERE id = :id");
        foreach ($colonnes as $colonne => $valeur) {
            $stmt->bindValue($colonne, $valeur, PDO::PARAM_BOOL);
        }
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateAvatar(int $id, string $avatar, bool $modele): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET avatar = :avatar, modele = :modele WHERE id = :id'
        );
        $stmt->bindValue('avatar', $avatar);
        // execute() would send a bare false as an empty string, which postgres refuses
        $stmt->bindValue('modele', $modele, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Closes an account for good: the montages go, the comments left elsewhere
     * stay on without their author.
     *
     * Foreign keys cascade over the rest — likes, reports, friendships, resets,
     * admin seat — but what has to survive or be counted is spelled out here.
     *
     * @return list<string> montage filenames, for the caller to unlink
     */
    public function delete(int $id): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT filename FROM images WHERE user_id = :id');
            $stmt->execute(['id' => $id]);
            $fichiers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->db->prepare('UPDATE comments SET user_id = NULL WHERE user_id = :id')
                ->execute(['id' => $id]);

            $siennes = '(SELECT id FROM images WHERE user_id = :id)';
            foreach (['likes', 'comments', 'reports'] as $table) {
                $this->db->prepare("DELETE FROM {$table} WHERE image_id IN {$siennes}")
                    ->execute(['id' => $id]);
            }

            $this->db->prepare('DELETE FROM images WHERE user_id = :id')->execute(['id' => $id]);
            $this->db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);

            $this->db->commit();

            return array_map('strval', $fichiers);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
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
