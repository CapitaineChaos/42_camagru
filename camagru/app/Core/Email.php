<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The email rules, shared by the forms and the HTML pattern.
 *
 * type="email" alone accepts a@b and toto@localhost, which filter_var refuses:
 * the browser would let through what the server rejects. The server applies both
 * checks and the form carries the same expression, so the two agree.
 */
final class Email
{
    public const PATTERN = '[^@\s]+@[^@\s]+\.[A-Za-z]{2,}';

    /** @return list<string> the unmet rules, empty when the address passes */
    public static function errors(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)
            || !preg_match('/^' . self::PATTERN . '$/', $email)) {
            return ['Invalid email address.'];
        }

        return [];
    }

    public static function hint(): string
    {
        return 'name@example.com';
    }
}
