<?php
/** @var string[] $errors */
/** @var array<string, string> $old */
/** @var string $notice */
?>
<section class="bloc bloc-etroit">
    <?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

    <p class="note">The link stays valid for
        <?= htmlspecialchars((string) round((int) \App\Core\Settings::get('auth.password_reset_ttl', 86400) / 3600)) ?> hours.</p>

    <form class="formulaire" method="post" action="/forgot-password">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
        </p>
        <p class="actions"><button type="submit">Send the link</button></p>
    </form>

    <p class="liens-annexes">
        <a href="/login">Back to login</a>
    </p>
</section>
