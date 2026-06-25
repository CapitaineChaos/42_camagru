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
</head>
<body>
    <nav>
        <a href="/">Camagru</a>
        <?php if (!empty($_SESSION['user'])): ?>
            <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <form action="/logout" method="post" style="display:inline">
                <button type="submit">Déconnexion</button>
            </form>
        <?php else: ?>
            <a href="/login">Connexion</a>
            <a href="/register">Inscription</a>
        <?php endif; ?>
    </nav>

    <main>
        <?= $content ?>
    </main>
</body>
</html>
