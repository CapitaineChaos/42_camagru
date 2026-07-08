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
                <!-- <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                <form action="/logout" method="post" style="display:inline">
                    <button type="submit">Déconnexion</button>
                </form> -->
                <li><a href="/preferences">Préférences</a></li>
                <li><a href="/profile">Profile</a></li>
                <li><a href="/logout">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="/login">Connexion</a></li>
                <li><a href="/register">Inscription</a></li>
            <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
