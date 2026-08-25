<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** What readers flagged, waiting for an admin to look at it. */
final class Report extends Model
{
    /** @return bool false when this reader had already flagged that montage */
    public function create(int $imageId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO reports (image_id, user_id) VALUES (:image_id, :user_id)
             ON CONFLICT (image_id, user_id) DO NOTHING'
        );
        $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);

        return $stmt->rowCount() === 1;
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT count(DISTINCT image_id) FROM reports')->fetchColumn();
    }

    /**
     * Flagged montages, the most reported first.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->db->query(
            'SELECT i.id, i.created_at, u.username,
                    count(r.id)     AS reports,
                    max(r.created_at) AS last_report
             FROM reports r
             JOIN images i ON i.id = r.image_id
             JOIN users u  ON u.id = i.user_id
             GROUP BY i.id, i.created_at, u.username
             ORDER BY count(r.id) DESC, max(r.created_at) DESC'
        )->fetchAll();
    }

    /** @return bool false when nothing was flagged on that montage */
    public function dismiss(int $imageId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM reports WHERE image_id = :image_id');
        $stmt->execute(['image_id' => $imageId]);

        return $stmt->rowCount() > 0;
    }
}
