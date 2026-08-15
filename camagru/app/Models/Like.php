<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Like extends Model
{
    /** @return bool true when the like was added, false when it was taken back */
    public function toggle(int $imageId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO likes (image_id, user_id) VALUES (:image_id, :user_id)
             ON CONFLICT (image_id, user_id) DO NOTHING'
        );
        $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);

        if ($stmt->rowCount() === 1) {
            return true;
        }

        $this->db->prepare('DELETE FROM likes WHERE image_id = :image_id AND user_id = :user_id')
            ->execute(['image_id' => $imageId, 'user_id' => $userId]);

        return false;
    }
}
