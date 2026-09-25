<?php
/** @var array{username: string, email: string} $compte */
/** @var array<string, bool> $reglages */

$minimum = (int) \App\Core\Settings::get('auth.password_min_length', 8);
?>
<?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

<section class="card">
    <h2>Credentials</h2>
    <form class="form-block flex-vt" method="post" action="/preferences/account">
        <?= \App\Core\Csrf::field() ?>
        <p class="field flex-vt tight">
            <label for="pseudo">Username</label>
            <input type="text" id="pseudo" name="username" autocomplete="username"
                   value="<?= htmlspecialchars($compte['username']) ?>" required
                   minlength="<?= \App\Core\Username::MINIMUM ?>"
                   maxlength="<?= \App\Core\Username::MAXIMUM ?>"
                   pattern="<?= htmlspecialchars(\App\Core\Username::pattern()) ?>"
                   title="<?= htmlspecialchars(\App\Core\Username::hint()) ?>">
        </p>
        <p class="field flex-vt tight">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" autocomplete="email"
                   value="<?= htmlspecialchars($compte['email']) ?>" required
                   pattern="<?= htmlspecialchars(\App\Core\Email::PATTERN) ?>"
                   title="<?= htmlspecialchars(\App\Core\Email::hint()) ?>">
        </p>
        <p class="field flex-vt tight">
            <label for="motdepasse">New password</label>
            <input type="password" id="motdepasse" name="password" autocomplete="new-password"
                   minlength="<?= $minimum ?>"
                   pattern="<?= htmlspecialchars(\App\Core\Password::pattern()) ?>"
                   title="<?= htmlspecialchars(\App\Core\Password::hint()) ?>">
            <span class="hint">Leave empty to keep the current one. At least <?= $minimum ?> characters, with one letter and one digit.</span>
        </p>
        <p class="field flex-vt tight">
            <label for="actuel">Current password</label>
            <input type="password" id="actuel" name="current_password"
                   autocomplete="current-password" required>
        </p>
        <p class="flex-hz"><button type="submit">Save</button></p>
    </form>
</section>

<section class="card">
    <h2>Notifications</h2>
    <form class="form-block flex-vt" method="post" action="/preferences/notifications">
        <?= \App\Core\Csrf::field() ?>
        <?php foreach (\App\Services\Notifications::REGLAGES as $colonne => $libelle): ?>
        <p class="field flex-hz">
            <input type="checkbox" id="<?= $colonne ?>" name="<?= $colonne ?>"
                   <?= !empty($reglages[$colonne]) ? 'checked' : '' ?>>
            <label for="<?= $colonne ?>" class="normal"><?= htmlspecialchars($libelle) ?></label>
        </p>
        <?php endforeach; ?>
        <p class="flex-hz"><button type="submit">Save</button></p>
    </form>
</section>

<section class="card card-danger">
    <h2>Delete account</h2>
    <p class="note">Your montages go with it. The comments you left on other montages
        stay there, without your name.</p>
    <form class="form-block flex-vt" method="post" action="/preferences/delete">
        <?= \App\Core\Csrf::field() ?>
        <p class="field flex-vt tight">
            <label for="suppression">Current password</label>
            <input type="password" id="suppression" name="current_password"
                   autocomplete="current-password" required>
        </p>
        <p class="field flex-hz">
            <input type="checkbox" id="confirm" name="confirm" required>
            <label for="confirm" class="normal">Delete my account for good</label>
        </p>
        <p class="flex-hz"><button type="submit" class="button-danger">Delete</button></p>
    </form>
</section>
