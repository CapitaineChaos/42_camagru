<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Friendship;

final class LayoutDataProvider
{
    private CurrentUser $currentUser;

    public function __construct(?CurrentUser $currentUser = null)
    {
        $this->currentUser = $currentUser ?? new CurrentUser();
    }

    /**
     * @param array<string, mixed> $session
     * @return array{currentUser: array<string, mixed>|null, currentUserAvatarUrl: string|null,
     *               pendingRequests: int}
     */
    public function fromSession(array $session): array
    {
        $user = $this->currentUser->fromSession($session);

        return [
            'currentUser' => $user,
            'currentUserAvatarUrl' => $user !== null ? $this->currentUser->avatarUrl($user) : null,
            'pendingRequests' => $user !== null
                ? (new Friendship())->pendingCount((int) $user['id'])
                : 0,
        ];
    }
}
