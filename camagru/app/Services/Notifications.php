<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Core\Pg;
use App\Models\User;

/** Emails the events a reader subscribed to; an event switched off sends nothing, without error. */
final class Notifications
{
    /** column => label on the preferences form */
    public const REGLAGES = [
        'notify_comment'         => 'Email me when someone comments one of my montages',
        'notify_friend_request'  => 'Email me when someone sends me a friend request',
        'notify_friend_accepted' => 'Email me when someone accepts my friend request',
        'notify_friend_removed'  => 'Email me when someone removes me from their friends',
    ];

    public function comment(int $ownerId, string $auteur, int $imageId): void
    {
        $this->send(
            $ownerId,
            'notify_comment',
            'New comment on your montage',
            htmlspecialchars($auteur) . ' commented one of your montages:<br>'
            . Mailer::link('/gallery#montage-' . $imageId)
        );
    }

    public function friendRequest(int $addresseeId, string $demandeur): void
    {
        $this->send(
            $addresseeId,
            'notify_friend_request',
            'New friend request',
            htmlspecialchars($demandeur) . ' wants to be your friend:<br>'
            . Mailer::link('/friends')
        );
    }

    public function friendAccepted(int $requesterId, string $accepteur): void
    {
        $this->send(
            $requesterId,
            'notify_friend_accepted',
            'Friend request accepted',
            htmlspecialchars($accepteur) . ' accepted your friend request:<br>'
            . Mailer::link('/friends')
        );
    }

    public function friendRemoved(int $userId, string $ancien): void
    {
        $this->send(
            $userId,
            'notify_friend_removed',
            'A friend removed you',
            htmlspecialchars($ancien) . ' is no longer in your friends.'
        );
    }

    private function send(int $userId, string $reglage, string $sujet, string $corps): void
    {
        $user = (new User())->findById($userId);

        if ($user === null || !Pg::bool($user[$reglage] ?? null)) {
            return;
        }

        Mailer::sendOrLog((string) $user['email'], (string) $user['username'], $sujet, $corps);
    }
}
