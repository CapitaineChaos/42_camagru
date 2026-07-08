<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class CurrentUser
{
    private User $users;

    public function __construct(?User $users = null)
    {
        $this->users = $users ?? new User();
    }

    /** @param array<string, mixed> $session */
    public function fromSession(array $session): ?array
    {
        $username = (string) ($session['user']['username'] ?? '');
        if ($username === '') {
            return null;
        }

        return $this->users->findByUsername($username);
    }

    /** @param array<string, mixed> $user */
    public function avatarUrl(array $user): string
    {
        $avatar = $this->avatarFilename($user);

        if ($this->usesModelAvatar($user)) {
            return '/avatars/' . rawurlencode($avatar);
        }

        return '/avatar';
    }

    /** @param array<string, mixed> $user */
    public function avatarFilename(array $user): string
    {
        $avatar = basename((string) ($user['avatar'] ?? ''));

        return $avatar !== '' ? $avatar : 'generique.png';
    }

    /** @param array<string, mixed> $user */
    public function usesModelAvatar(array $user): bool
    {
        return in_array($user['modele'] ?? null, [true, 't', '1', 1], true);
    }
}
