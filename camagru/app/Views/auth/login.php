<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
/** @var string $notice */
?>
<section class="card card-narrow">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <form class="form-block" method="post" action="/login">
        <?= \App\Core\Csrf::field() ?>
        <p class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
        </p>
        <p class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </p>
        <p class="actions"><button type="submit">Log in</button></p>
    </form>

    <p class="side-links">
        <a href="/forgot-password">Forgot your password?</a>
        <a href="/register">Create an account</a>
    </p>
</section>
