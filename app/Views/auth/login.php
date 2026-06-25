<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
?>

<h1>Connexion</h1>

<?php foreach ($errors ?? [] as $error): ?>
    <p style="color:red"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form action="/login" method="post">
    <p>
        <label>Email
            <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
        </label>
    </p>
    <p>
        <label>Mot de passe
            <input type="password" name="password" required>
        </label>
    </p>
    <button type="submit">Se connecter</button>
</form>
