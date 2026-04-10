<?php
declare(strict_types=1);

/**
 * Seed 26 users (de a à z), already verified.
 *
 * Pattern:
 *   username : aaa, bbb, ..., zzz
 *   email    : aaa@aaa.com, bbb@bbb.com, ...
 *   password : aaaaaaA1, bbbbbbB1, ...  (6× lower + upper + '1')
 *   verified : 1
 *
 * Usage (depuis la racine du projet) :
 *   make seed
 */

$dbPath = '/var/www/html/data/auth.sqlite';

if (!file_exists($dbPath)) {
    fwrite(STDERR, "DB introuvable - le service auth doit être sollicité avant le seed.\n");
    exit(1);
}

$pdo = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$insert = $pdo->prepare(
    'INSERT OR IGNORE INTO users (username, email, password_hash, verified)
     VALUES (:username, :email, :hash, 1)'
);

$created = 0;
$skipped = 0;

foreach (range('a', 'z') as $letter) {
    $triple   = str_repeat($letter, 3);
    $username = $triple;
    $email    = "{$triple}@{$triple}.com";
    $password = str_repeat($letter, 6) . strtoupper($letter) . '1';
    $hash     = password_hash($password, PASSWORD_BCRYPT);

    $insert->execute([
        'username' => $username,
        'email'    => $email,
        'hash'     => $hash,
    ]);

    if ($insert->rowCount() > 0) {
        echo "  + {$username}  /  {$email}  /  {$password}\n";
        $created++;
    } else {
        echo "  ~ {$username} already exists, skipped.\n";
        $skipped++;
    }
}

echo "\nDone - {$created} created, {$skipped} skipped.\n";
