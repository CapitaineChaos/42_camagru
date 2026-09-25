<?php

declare(strict_types=1);

namespace App\Core;

/** The password policy, in one place: inscription, reset and preferences share it. */
final class Password
{
    /** @return list<string> the unmet rules, empty when the password passes */
    public static function errors(string $password): array
    {
        $minimum = self::minimum();

        $errors = [];
        if (strlen($password) < $minimum) {
            $errors[] = 'Password must be at least ' . $minimum . ' characters long.';
        }
        // ASCII on purpose: the same classes are given to the HTML pattern below,
        // so the browser and the server accept exactly the same strings
        if (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'Password must contain at least one letter.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }

        return $errors;
    }

    /** The same rules as an HTML pattern attribute, anchored by the browser. */
    public static function pattern(): string
    {
        return '(?=.*[A-Za-z])(?=.*\d).{' . self::minimum() . ',}';
    }

    public static function hint(): string
    {
        return 'At least ' . self::minimum() . ' characters, with one letter and one digit.';
    }

    public static function minimum(): int
    {
        return (int) Settings::get('auth.password_min_length', 8);
    }
}
