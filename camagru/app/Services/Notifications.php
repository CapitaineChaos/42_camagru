<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Core\Pg;
use App\Models\User;

/** Emails the events a reader asked to hear about; silence is the setting, not a failure. */
final class Notifications
{
    /** column => label on the preferences form */
    public const REGLAGES = [
        'notify_comment'         => 'Email me when someone comments one of my montages',
        'notify_friend_request'  => 'Email me when someone sends me a friend request',
        'notify_friend_accepted' => 'Email me when someone accepts my friend request',
        'notify_friend_removed'  => 'Email me when someone removes me from their friends',
    ];

    /** @var list<string> */
    public const COLONNES = [
        'notify_comment', 'notify_friend_request',
        'notify_friend_accepted', 'notify_friend_removed',
    ];

    public function comment(int $ownerId, string $auteur, int $imageId): void
    {
        $this->send(
            $ownerId,
            'notify_comment',
            'New comment on your montage',
            htmlspecialchars($auteur) . ' commented one of your montages:<br>'
            . $this->lien('/gallery#montage-' . $imageId)
        );
    }

    public function friendRequest(int $addresseeId, string $demandeur): void
    {
        $this->send(
            $addresseeId,
            'notify_friend_request',
            'New friend request',
            htmlspecialchars($demandeur) . ' wants to be your friend:<br>'
            . $this->lien('/friends')
        );
    }

    public function friendAccepted(int $requesterId, string $accepteur): void
    {
        $this->send(
            $requesterId,
            'notify_friend_accepted',
            'Friend request accepted',
            htmlspecialchars($accepteur) . ' accepted your friend request:<br>'
            . $this->lien('/friends')
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

        Mailer::sendOrLog(
            (string) $user['email'],
            $sujet,
            'Hi ' . htmlspecialchars((string) $user['username']) . ',<br><br>' . $corps
        );
    }

    private function lien(string $chemin): string
    {
        $url = APP_URL . $chemin;

        return '<a href="' . $url . '">' . $url . '</a>';
    }
}
