<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/** The stickers on offer: photobooth.stickers in settings.php, files in public/stickers/. */
final class Overlays
{
    /** @return list<array{slug: string, label: string, url: string}> */
    public function catalogue(): array
    {
        $version = (int) Settings::get('assets.version');
        $entrees = [];

        foreach ((array) Settings::get('photobooth.stickers') as $slug => $label) {
            if ($this->path((string) $slug) === null) {
                continue;
            }
            $entrees[] = [
                'slug'  => (string) $slug,
                'label' => (string) $label,
                'url'   => '/stickers/' . $slug . '.png?v=' . $version,
            ];
        }

        return $entrees;
    }

    /** @return string|null absolute path, null when the slug is not in the catalogue or has no file */
    public function path(string $slug): ?string
    {
        if (!array_key_exists($slug, (array) Settings::get('photobooth.stickers'))) {
            return null;
        }

        $fichier = BASE_PATH . '/public/stickers/' . $slug . '.png';

        return is_file($fichier) ? $fichier : null;
    }
}
