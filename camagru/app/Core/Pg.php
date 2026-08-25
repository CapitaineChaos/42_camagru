<?php

declare(strict_types=1);

namespace App\Core;

final class Pg
{
    /** PDO_pgsql hands booleans back as 't' / 'f'. */
    public static function bool(mixed $valeur): bool
    {
        return in_array($valeur, [true, 't', '1', 1], true);
    }
}
