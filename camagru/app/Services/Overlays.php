<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Catalogue of the superimposable images, one family per folder under public/.
 *
 * filtres/ face filters drawn by scripts/filtres.py
 */
final class Overlays
{
    /** settings key => [folder under public/, section heading] */
    private const FAMILLES = [
        'filters' => ['filtres', 'Filters'],
    ];

    /**
     * @return list<array{titre: string,
     *                    entrees: list<array{slug: string, label: string, url: string}>}>
     */
    public function catalogue(): array
    {
        $version = (int) Settings::get('assets.version', 1);

        $familles = [];
        foreach (self::FAMILLES as $cle => [$dossier, $titre]) {
            $entrees = [];
            foreach ((array) Settings::get('photobooth.' . $cle, []) as $slug => $label) {
                if ($this->path((string) $slug) === null) {
                    continue;
                }
                $entrees[] = [
                    'slug'  => (string) $slug,
                    'label' => (string) $label,
                    'url'   => '/' . $dossier . '/' . $slug . '.png?v=' . $version,
                ];
            }
            if ($entrees !== []) {
                $familles[] = ['titre' => $titre, 'entrees' => $entrees];
            }
        }

        return $familles;
    }

    /** @return string|null absolute path, null when the slug is in no catalogue */
    public function path(string $slug): ?string
    {
        foreach (self::FAMILLES as $cle => [$dossier, $titre]) {
            if (!array_key_exists($slug, (array) Settings::get('photobooth.' . $cle, []))) {
                continue;
            }

            $fichier = BASE_PATH . '/public/' . $dossier . '/' . $slug . '.png';
            if (is_file($fichier)) {
                return $fichier;
            }
        }

        return null;
    }
}
