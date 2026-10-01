<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Friendship;
use App\Services\CurrentUser;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = []): void
    {
        $data += $this->layoutData();

        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/app/Views/layout.php';
    }

    /** What the layout shows on every page: the account, its avatar, pending friend requests. */
    private function layoutData(): array
    {
        $comptes = new CurrentUser();
        $user = $comptes->fromSession($_SESSION);

        return [
            'currentUser'          => $user,
            'currentUserAvatarUrl' => $user !== null ? $comptes->avatarUrl($user) : null,
            'pendingRequests'      => $user !== null ? (new Friendship())->pendingCount((int) $user['id']) : 0,
        ];
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    /** The logged-in reader's id, 0 when logged out. */
    protected function viewerId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function lifetimeInWords(int $seconds): string
    {
        $hours = (int) round($seconds / 3600);

        return $hours === 1 ? '1 hour' : $hours . ' hours';
    }
}
