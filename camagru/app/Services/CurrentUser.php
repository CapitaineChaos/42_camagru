<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Pg;
use App\Core\Settings;
use App\Models\User;

final class CurrentUser
{
    /** @param array<string, mixed> $session */
    public function fromSession(array $session): ?array
    {
        $id = (int) ($session['user']['id'] ?? 0);

        return $id > 0 ? (new User())->findById($id) : null;
    }

    /** @param array<string, mixed> $user */
    public function avatarUrl(array $user): string
    {
        $avatar = $this->avatarFilename($user);

        if ($this->usesModelAvatar($user)) {
            return '/avatars/' . rawurlencode($avatar);
        }

        return '/avatar?id=' . (int) $user['id'];
    }

    /** @param array<string, mixed> $user */
    public function avatarFilename(array $user): string
    {
        $avatar = basename((string) ($user['avatar'] ?? ''));

        return $avatar !== '' ? $avatar : (string) Settings::get('avatars.default');
    }

    /** @param array<string, mixed> $user */
    public function usesModelAvatar(array $user): bool
    {
        return Pg::bool($user['modele'] ?? null);
    }
}
