<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
?>
<section class="bloc bloc-etroit">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <form class="formulaire" method="post" action="/register">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" autocomplete="username" required>
        </p>
        <p class="champ">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
        </p>
        <p class="champ">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required
                   minlength="<?= (int) \App\Core\Settings::get('auth.password_min_length', 8) ?>">
        </p>
        <p class="actions"><button type="submit">Sign up</button></p>
    </form>

    <p class="liens-annexes">
        <a href="/login">Already registered? Log in</a>
    </p>
</section>
