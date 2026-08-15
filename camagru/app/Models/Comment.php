<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Comment extends Model
{
    public function create(int $imageId, int $userId, string $texte): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO comments (image_id, user_id, comment)
             VALUES (:image_id, :user_id, :comment)'
        );
        $stmt->execute(['image_id' => $imageId, 'user_id' => $userId, 'comment' => $texte]);
    }

    /**
     * One query for the whole page, oldest first.
     *
     * @param list<int> $imageIds
     * @return array<int, list<array<string, mixed>>> keyed by image id
     */
    public function forImages(array $imageIds): array
    {
        if ($imageIds === []) {
            return [];
        }

        $marques = implode(',', array_fill(0, count($imageIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT c.image_id, c.comment, c.created_at, u.username
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.image_id IN ({$marques})
             ORDER BY c.created_at, c.id"
        );
        $stmt->execute($imageIds);

        $parImage = [];
        foreach ($stmt->fetchAll() as $commentaire) {
            $parImage[(int) $commentaire['image_id']][] = $commentaire;
        }

        return $parImage;
    }
}
