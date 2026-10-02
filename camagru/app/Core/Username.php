<?php

declare(strict_types=1);

namespace App\Core;

/** The username rules, shared by the sign-up form, the preferences and the HTML pattern. */
final class Username
{
    public const MINIMUM = 3;
    public const MAXIMUM = 50;   // users.username is VARCHAR(50)

    /** @return list<string> the unmet rules, empty when the username passes */
    public static function errors(string $username): array
    {
        $errors = [];
        if (mb_strlen($username) < self::MINIMUM || mb_strlen($username) > self::MAXIMUM) {
            $errors[] = 'Username must be between ' . self::MINIMUM . ' and ' . self::MAXIMUM . ' characters long.';
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $username)) {
            $errors[] = 'Username starts with a letter or a digit, then accepts letters, digits, dot, dash and underscore.';
        }

        return $errors;
    }

    /**
     * The same rules as an HTML pattern attribute, anchored by the browser.
     * Browsers compile it with the v flag, where an unescaped - in a class is a
     * syntax error and the whole attribute is dropped.
     */
    public static function pattern(): string
    {
        return '[A-Za-z0-9][A-Za-z0-9_.\-]{' . (self::MINIMUM - 1) . ',' . (self::MAXIMUM - 1) . '}';
    }

    public static function hint(): string
    {
        return self::MINIMUM . ' to ' . self::MAXIMUM . ' characters: letters, digits, dot, dash, underscore; '
            . 'a letter or a digit first.';
    }
}
