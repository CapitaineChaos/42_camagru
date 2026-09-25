<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
/** @var string $notice */
?>
<section class="card card-narrow">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <p class="note">The link stays valid for
        <?= htmlspecialchars((string) round((int) \App\Core\Settings::get('auth.password_reset_ttl', 86400) / 3600)) ?> hours.</p>

    <form class="form-block flex-vt" method="post" action="/forgot-password">
        <?= \App\Core\Csrf::field() ?>
        <p class="field flex-vt tight">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   autocomplete="email" required
                   pattern="<?= htmlspecialchars(\App\Core\Email::PATTERN) ?>"
                   title="<?= htmlspecialchars(\App\Core\Email::hint()) ?>">
        </p>
        <p class="flex-hz"><button type="submit">Send the link</button></p>
    </form>

    <p class="side-links flex-hz">
        <a href="/login">Back to login</a>
    </p>
</section>
