<h1>Bienvenue sur Camagru</h1>

<?php if (!empty($_SESSION['user'])): ?>
    <p>Connecté en tant que <strong><?= htmlspecialchars($_SESSION['user']['username']) ?></strong>.</p>
<?php else: ?>
    <p>Se connecter ou créer un compte pour commencer.</p>
<?php endif; ?>
