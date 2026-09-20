<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
/** @var string $notice */
?>
<section class="card card-narrow">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <form class="form-block flex-vt" method="post" action="/login">
        <?= \App\Core\Csrf::field() ?>
        <p class="field flex-vt tight">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" autocomplete="username" required>
        </p>
        <p class="field flex-vt tight">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </p>
        <p class="flex-hz"><button type="submit">Log in</button></p>
    </form>

    <p class="side-links flex-hz">
        <a href="/forgot-password">Forgot your password?</a>
        <a href="/register">Create an account</a>
    </p>
</section>
