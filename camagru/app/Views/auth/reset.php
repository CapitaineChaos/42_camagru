<?php
/** @var string[] $errors */
/** @var string $token */
?>
<section class="card card-narrow">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <form class="form-block flex-vt" method="post" action="/reset-password">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
        <p class="field flex-vt tight">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required
                   minlength="<?= (int) \App\Core\Settings::get('auth.password_min_length', 8) ?>">
        </p>
        <p class="field flex-vt tight">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </p>
        <p class="flex-hz"><button type="submit">Save</button></p>
    </form>
</section>
