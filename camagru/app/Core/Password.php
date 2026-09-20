<?php

declare(strict_types=1);

namespace App\Core;

/** The password policy, in one place: inscription, reset and preferences share it. */
final class Password
{
    /** @return list<string> the unmet rules, empty when the password passes */
    public static function errors(string $password): array
    {
        $minimum = (int) Settings::get('auth.password_min_length', 8);

        $errors = [];
        if (strlen($password) < $minimum) {
            $errors[] = 'Password must be at least ' . $minimum . ' characters long.';
        }
        if (!preg_match('/\p{L}/u', $password)) {
            $errors[] = 'Password must contain at least one letter.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }

        return $errors;
    }
}
