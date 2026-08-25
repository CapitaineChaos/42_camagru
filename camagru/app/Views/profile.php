<?php
/** @var array<string, mixed>|null $currentUser */
/** @var string|null $currentUserAvatarUrl */
/** @var list<string> $modeles */
/** @var list<array<string, mixed>> $montages */
/** @var string $avatarCourant */
/** @var bool $avatarModele */

$since = static fn (string $horodatage): string
    => date('j M Y', (int) strtotime($horodatage));
?>
<?php if (!empty($notice)): ?>
<p class="notice"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
<p class="error"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>

<section class="card card-identity">
    <h2>Account</h2>
    <div class="identity">
        <img class="avatar-large" src="<?= htmlspecialchars((string) $currentUserAvatarUrl) ?>"
             alt="Avatar of <?= htmlspecialchars((string) $currentUser['username']) ?>">
        <dl class="details">
            <dt>Username</dt><dd><?= htmlspecialchars((string) $currentUser['username']) ?></dd>
            <dt>Email address</dt><dd><?= htmlspecialchars((string) $currentUser['email']) ?></dd>
            <dt>Member since</dt><dd><?= htmlspecialchars($since((string) $currentUser['created_at'])) ?></dd>
        </dl>
    </div>
</section>

<section class="card">
    <h2>Avatar</h2>
    <form method="post" action="/profile/avatar">
        <?= \App\Core\Csrf::field() ?>
        <ul class="avatars">
            <?php foreach ($modeles as $rang => $modele): ?>
            <?php $porte = $avatarModele && $modele === $avatarCourant; ?>
            <li>
                <button type="submit" name="model" value="<?= htmlspecialchars($modele) ?>"
                        class="avatar-choice<?= $porte ? ' chosen' : '' ?>"
                        aria-label="Avatar <?= $rang + 1 ?>"<?= $porte ? ' aria-current="true"' : '' ?>>
                    <img src="/avatars/<?= rawurlencode($modele) ?>" alt="" loading="lazy">
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </form>
</section>

<section class="card">
    <h2>Montages</h2>
    <?php if ($montages === []): ?>
    <p class="note">No montage yet. <a href="/photobooth">Take the first one.</a></p>
    <?php else: ?>
    <ul class="thumbs">
        <?php foreach ($montages as $image): ?>
        <?php $id = (int) $image['id']; ?>
        <li class="thumb">
            <img src="/photo?id=<?= $id ?>" loading="lazy"
                 alt="Montage of <?= htmlspecialchars($since((string) $image['created_at'])) ?>">
            <div class="thumb-footer">
                <span class="counts"><?= \App\Core\Text::plural((int) $image['likes'], 'like') ?>, <?= \App\Core\Text::plural((int) $image['comments'], 'comment') ?></span>
                <form method="post" action="/profile/avatar">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" name="montage" value="<?= $id ?>" class="button-quiet">Use as avatar</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
