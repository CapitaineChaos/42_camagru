<?php

declare(strict_types=1);

namespace App\Services;

final class LayoutDataProvider
{
    private CurrentUser $currentUser;

    public function __construct(?CurrentUser $currentUser = null)
    {
        $this->currentUser = $currentUser ?? new CurrentUser();
    }

    /**
     * @param array<string, mixed> $session
     * @return array{currentUser: array<string, mixed>|null, currentUserAvatarUrl: string|null}
     */
    public function fromSession(array $session): array
    {
        $user = $this->currentUser->fromSession($session);

        return [
            'currentUser' => $user,
            'currentUserAvatarUrl' => $user !== null ? $this->currentUser->avatarUrl($user) : null,
        ];
    }
}
