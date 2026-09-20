<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
?>
<section class="card card-narrow">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <form class="form-block flex-vt" method="post" action="/register">
        <?= \App\Core\Csrf::field() ?>
        <p class="field flex-vt tight">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" autocomplete="username" required>
        </p>
        <p class="field flex-vt tight">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
        </p>
        <p class="field flex-vt tight">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required
                   minlength="<?= (int) \App\Core\Settings::get('auth.password_min_length', 8) ?>">
        </p>
        <p class="flex-hz"><button type="submit">Sign up</button></p>
    </form>

    <p class="side-links flex-hz">
        <a href="/login">Already registered? Log in</a>
    </p>
</section>
