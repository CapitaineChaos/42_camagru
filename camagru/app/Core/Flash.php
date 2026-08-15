<?php

declare(strict_types=1);

namespace App\Core;

/** Messages surviving one redirect, so a POST never replays on reload. */
final class Flash
{
    private const SESSION_KEY = 'flash';

    public static function notice(string $message): void
    {
        $_SESSION[self::SESSION_KEY]['notice'] = $message;
    }

    /** @param list<string> $messages */
    public static function errors(array $messages): void
    {
        $_SESSION[self::SESSION_KEY]['errors'] = $messages;
    }

    /** @return array{notice?: string, errors?: list<string>} */
    public static function pull(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return $messages;
    }
}
