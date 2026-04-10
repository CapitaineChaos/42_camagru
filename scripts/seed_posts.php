<?php
declare(strict_types=1);

/**
 * Seed la base posts SQLite avec un post par image seedée.
 *
 * - Lit le manifest depuis /manifest.json (monté depuis scripts/avatars/manifest.json)
 * - Écrit dans /var/www/html/data/post.sqlite (volume post-data)
 * - URL des images : /api/media/uploads/{triple}-{n}.png
 * - Idempotent via UNIQUE sur image_url
 *
 * Usage (depuis la racine) :
 *   make seed-posts
 */

$manifestPath = '/manifest.json';
$dbPath       = '/var/www/html/data/post.sqlite';

if (!file_exists($manifestPath)) {
    fwrite(STDERR, "manifest.json introuvable. Lancez 'make seed-avatars' d'abord.\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "manifest.json invalide.\n");
    exit(1);
}

if (!file_exists($dbPath)) {
    fwrite(STDERR, "DB introuvable - le service post doit être sollicité avant le seed.\n");
    exit(1);
}

$pdo = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$insert = $pdo->prepare("
    INSERT OR IGNORE INTO posts (user_id, username, image_url, created_at)
    VALUES (:uid, :username, :image_url, datetime('now', :offset))
");

$letters  = range('a', 'z');
$created  = 0;
$skipped  = 0;
// Spread posts over the past year, backwards from now
$offsetMin = 0;

foreach ($letters as $idx => $letter) {
    $triple  = str_repeat($letter, 3);
    $userId  = $idx + 1;          // aaa=1 ... zzz=26 (order of seed_users.php)
    $count   = (int) ($manifest[$triple] ?? 0);

    for ($i = 1; $i <= $count; $i++) {
        $imageUrl  = "/api/media/uploads/{$triple}-{$i}.png";
        $offsetMin += rand(60, 480);   // 1h–8h between posts

        $insert->execute([
            'uid'       => $userId,
            'username'  => $triple,
            'image_url' => $imageUrl,
            'offset'    => "-{$offsetMin} minutes",
        ]);

        if ($insert->rowCount() > 0) {
            $created++;
        } else {
            $skipped++;
        }
    }

    if ($count > 0) {
        echo "  + $triple → $count post(s)\n";
    }
}

echo "\nTerminé - $created créés, $skipped déjà présents.\n";
