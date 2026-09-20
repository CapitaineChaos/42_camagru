<?php

declare(strict_types=1);

/**
 * Create or update the Camagru admin account from the secrets.
 *
 * The schema no longer carries any credential: the account is born here, with a
 * hash computed by PHP, from admin_user / admin_email / admin_password. Those
 * three have nothing to do with db_user / db_password, which are the Postgres
 * role the application connects with.
 *
 *     make admin          # in the container
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/config.php';
require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;
use App\Core\Secret;

$username = Secret::read('admin_user');
$email    = Secret::read('admin_email');
$password = Secret::read('admin_password');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "secrets/admin_email n'est pas une adresse valide : $email\n");
    exit(1);
}

$pdo = Database::pdo();
$pdo->beginTransaction();

// re-running the script rotates the password instead of failing
$compte = $pdo->prepare(
    'INSERT INTO users (username, email, password, avatar, modele, verified)
     VALUES (:username, :email, :password, :avatar, TRUE, TRUE)
     ON CONFLICT (email) DO UPDATE
        SET username = EXCLUDED.username,
            password = EXCLUDED.password,
            verified = TRUE'
);
$compte->execute([
    'username' => $username,
    'email'    => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'avatar'   => 'generique.png',
]);

// admins has no unique key on user_id: the NOT EXISTS keeps the row unique
$promotion = $pdo->prepare(
    'INSERT INTO admins (user_id)
     SELECT u.id FROM users u
     WHERE u.email = :email
       AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id)'
);
$promotion->execute(['email' => $email]);

$pdo->commit();

echo "admin $username <$email> prêt\n";
