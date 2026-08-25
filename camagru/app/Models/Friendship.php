<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** One row per pair of users: pending while accepted_at is null, friends once it is set. */
final class Friendship extends Model
{
    public const SENT      = 'sent';
    public const ACCEPTED  = 'accepted';
    public const KNOWN     = 'known';     // a request is already waiting between the two
    public const FRIENDS   = 'friends';
    public const SELF      = 'self';

    public const INCOMING  = 'incoming';
    public const OUTGOING  = 'outgoing';
    public const FRIEND    = 'friend';

    public function pendingCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT count(*) FROM friendships WHERE addressee_id = :me AND accepted_at IS NULL'
        );
        $stmt->execute(['me' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Every relation this user is part of, seen from their side.
     *
     * @return list<array<string, mixed>> the other party, plus a state among
     *                                    incoming, outgoing and friend
     */
    public function all(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.avatar, u.modele, f.created_at,
                    CASE WHEN f.accepted_at IS NOT NULL THEN 'friend'
                         WHEN f.addressee_id = :me      THEN 'incoming'
                         ELSE 'outgoing' END AS state
             FROM friendships f
             JOIN users u
               ON u.id = CASE WHEN f.requester_id = :me THEN f.addressee_id ELSE f.requester_id END
             WHERE f.requester_id = :me OR f.addressee_id = :me
             ORDER BY u.username"
        );
        $stmt->execute(['me' => $userId]);

        return $stmt->fetchAll();
    }

    /** @return string one of SENT, ACCEPTED, KNOWN, FRIENDS, SELF */
    public function request(int $requesterId, int $addresseeId): string
    {
        if ($requesterId === $addresseeId) {
            return self::SELF;
        }

        $existante = $this->between($requesterId, $addresseeId);

        if ($existante !== null) {
            if ($existante['accepted_at'] !== null) {
                return self::FRIENDS;
            }

            // asking back someone who asked first is an answer, not a second request
            if ((int) $existante['requester_id'] === $addresseeId) {
                return $this->accept($requesterId, $addresseeId) ? self::ACCEPTED : self::FRIENDS;
            }

            return self::KNOWN;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO friendships (requester_id, addressee_id) VALUES (:requester, :addressee)
             ON CONFLICT DO NOTHING'
        );
        $stmt->execute(['requester' => $requesterId, 'addressee' => $addresseeId]);

        return $stmt->rowCount() === 1 ? self::SENT : self::KNOWN;
    }

    public function accept(int $userId, int $requesterId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE friendships SET accepted_at = now()
             WHERE requester_id = :requester AND addressee_id = :me AND accepted_at IS NULL'
        );
        $stmt->execute(['requester' => $requesterId, 'me' => $userId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Declining, cancelling and unfriending all drop the same row; the state it
     * held says which of the three happened.
     *
     * @return string|null one of INCOMING, OUTGOING, FRIEND; null when nothing was linking them
     */
    public function remove(int $userId, int $otherId): ?string
    {
        $stmt = $this->db->prepare(
            "DELETE FROM friendships
             WHERE (requester_id = :me AND addressee_id = :other)
                OR (requester_id = :other AND addressee_id = :me)
             RETURNING CASE WHEN accepted_at IS NOT NULL THEN 'friend'
                            WHEN addressee_id = :me      THEN 'incoming'
                            ELSE 'outgoing' END"
        );
        $stmt->execute(['me' => $userId, 'other' => $otherId]);

        $etat = $stmt->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    /** @return array<string, mixed>|null */
    private function between(int $a, int $b): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM friendships
             WHERE (requester_id = :a AND addressee_id = :b)
                OR (requester_id = :b AND addressee_id = :a)'
        );
        $stmt->execute(['a' => $a, 'b' => $b]);

        return $stmt->fetch() ?: null;
    }
}
