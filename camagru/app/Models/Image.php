<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

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
     * Drops a montage; likes, comments and reports go with the row, their
     * foreign keys cascade.
     *
     * @param int|null $userId restricts the deletion to that owner; null lifts the
     *                         check, for the admin desk
     * @return string|null filename of the deleted montage, null when nothing matched
     */
    public function delete(int $id, ?int $userId = null): ?string
    {
        if ($userId === null) {
            $stmt = $this->db->prepare('DELETE FROM images WHERE id = :id RETURNING filename');
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $this->db->prepare(
                'DELETE FROM images WHERE id = :id AND user_id = :user_id RETURNING filename'
            );
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
        }

        $filename = $stmt->fetchColumn();

        return $filename === false ? null : (string) $filename;
    }
}
