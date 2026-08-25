<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;
use Throwable;

final class Image extends Model
{
    public function create(int $userId, string $filename): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO images (user_id, filename) VALUES (:user_id, :filename) RETURNING id'
        );
        $stmt->execute(['user_id' => $userId, 'filename' => $filename]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, u.username
             FROM images i JOIN users u ON u.id = i.user_id
             WHERE i.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT count(*) FROM images')->fetchColumn();
    }

    /**
     * One page of the gallery, newest first.
     *
     * @param int|null $viewerId marks what the reader already liked; null when logged out
     * @return list<array<string, mixed>>
     */
    public function page(int $limit, int $offset, ?int $viewerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.filename, i.created_at, i.user_id, u.username,
                    (SELECT count(*) FROM likes l WHERE l.image_id = i.id)    AS likes,
                    (SELECT count(*) FROM comments c WHERE c.image_id = i.id) AS comments,
                    CAST(EXISTS (SELECT 1 FROM likes l
                                 WHERE l.image_id = i.id
                                   AND l.user_id = :viewer) AS INTEGER)       AS liked,
                    CAST(EXISTS (SELECT 1 FROM reports r
                                 WHERE r.image_id = i.id
                                   AND r.user_id = :viewer) AS INTEGER)       AS reported
             FROM images i JOIN users u ON u.id = i.user_id
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('viewer', $viewerId, $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.filename, i.created_at,
                    (SELECT count(*) FROM likes l WHERE l.image_id = i.id)    AS likes,
                    (SELECT count(*) FROM comments c WHERE c.image_id = i.id) AS comments
             FROM images i
             WHERE i.user_id = :user_id
             ORDER BY i.created_at DESC, i.id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Drop a montage and everything hanging from it, the owner only.
     *
     * @return string|null filename of the deleted montage, null when it is not the owner's
     */
    public function delete(int $id, int $userId): ?string
    {
        return $this->drop($id, $userId);
    }

    /** Same, whoever owns it: the admin desk answers to reports, not to ownership. */
    public function remove(int $id): ?string
    {
        return $this->drop($id, null);
    }

    /**
     * Likes, comments and reports are deleted by hand although the foreign keys
     * cascade: the model owns the rule, whatever the schema in place.
     *
     * @param int|null $userId restricts the deletion to that owner; null lifts the check
     */
    private function drop(int $id, ?int $userId): ?string
    {
        $this->db->beginTransaction();

        try {
            $possession = $userId === null ? '' : ' AND user_id = :user_id';
            $stmt = $this->db->prepare(
                'SELECT filename FROM images WHERE id = :id' . $possession . ' FOR UPDATE'
            );
            $stmt->execute(
                $userId === null ? ['id' => $id] : ['id' => $id, 'user_id' => $userId]
            );
            $filename = $stmt->fetchColumn();

            if ($filename === false) {
                $this->db->rollBack();
                return null;
            }

            foreach (['likes', 'comments', 'reports', 'images'] as $table) {
                $colonne = $table === 'images' ? 'id' : 'image_id';
                $this->db->prepare("DELETE FROM {$table} WHERE {$colonne} = :id")
                    ->execute(['id' => $id]);
            }

            $this->db->commit();

            return (string) $filename;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
