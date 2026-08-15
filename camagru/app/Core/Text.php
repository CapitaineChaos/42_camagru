<?php

declare(strict_types=1);

namespace App\Core;

final class Text
{
    /** Regular plurals only: the counters of the gallery. */
    public static function plural(int $nombre, string $nom): string
    {
        return $nombre . ' ' . $nom . ($nombre === 1 ? '' : 's');
    }
}
