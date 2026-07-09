<?php
/** @var string $content */
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Camagru') ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header>
        <input type="checkbox" id="burger" class="hamburger">
        <label for="burger"><span></span></label>
        <nav>
            <ul>
                <li><a href="/">Camagru</a></li>
                <li><a href="/gallery">Gallery</a></li>        
            <?php if (!empty($_SESSION['user'])): ?>
                <li><a href="/friends">Amis</a></li>
                <li><a href="/preferences">Préférences</a></li>
                <li><a href="/profile">Profile</a></li>
                <li>
                    <form action="/logout" method="post" class="logout-form">
                        <?= \App\Core\Csrf::field() ?>
                        <a href="/logout" onclick="event.preventDefault(); this.closest('form').submit();">Déconnexion</a>
                    </form>
                </li>
                <?php if (!empty($_SESSION['user']['is_admin'])): ?>
                    <li><a href="/admin">Admin</a></li>
                <?php endif; ?>
            <?php else: ?>
                <li><a href="/login">Connexion</a></li>
                <li><a href="/register">Inscription</a></li>
            <?php endif; ?>
            </ul>
        </nav>
        <?php if (!empty($currentUser) && !empty($currentUserAvatarUrl)): ?>
            <img src="<?= htmlspecialchars($currentUserAvatarUrl) ?>" alt="Avatar de <?= htmlspecialchars($currentUser['username']) ?>" class="avatar">
            <p>Connecté en tant que <?= htmlspecialchars($currentUser['username']) ?></p>
        <?php endif; ?>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> Camagru. Tous droits réservés.</p>
    </footer>
</body>
</html>
