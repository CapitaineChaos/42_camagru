<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
/** @var string $notice */
?>

<h1>Connexion</h1>

<?php if (!empty($notice)): ?>
    <p style="color:green"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>

<?php foreach ($errors ?? [] as $error): ?>
    <p style="color:red"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form action="/login" method="post">
    <?= \App\Core\Csrf::field() ?>
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
