<?php
/** @var list<array<string, mixed>> $images */
/** @var array<int, list<array<string, mixed>>> $commentaires */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var int|null $viewerId */
/** @var int $maxComment */

$quand = static fn (string $horodatage): string
    => date('j M Y, H:i', (int) strtotime($horodatage));
?>
<?php if (!empty($notice)): ?>
<p class="notice"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
<p class="error"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>

<?php if ($images === []): ?>
<section class="card">
    <p class="note">No montage yet.<?php if ($viewerId !== null): ?>
        <a href="/photobooth">Take the first one.</a><?php endif; ?></p>
</section>
<?php else: ?>

<?php foreach ($images as $image): ?>
<?php
$id = (int) $image['id'];
$sien = $viewerId !== null && $viewerId === (int) $image['user_id'];
?>
<article class="card montage-card" id="montage-<?= $id ?>">
    <h2><?= htmlspecialchars((string) $image['username']) ?>
        <span class="when"><?= htmlspecialchars($quand((string) $image['created_at'])) ?></span></h2>

    <img class="montage-view" src="/photo?id=<?= $id ?>" loading="lazy"
         alt="Montage by <?= htmlspecialchars((string) $image['username']) ?>">

    <div class="montage-actions">
        <?php if ($viewerId !== null && !$sien): ?>
        <form method="post" action="/gallery/like">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit"><?= (int) $image['liked'] === 1 ? 'Unlike' : 'Like' ?></button>
        </form>
        <?php endif; ?>
        <span class="counts"><?= \App\Core\Text::plural((int) $image['likes'], 'like') ?>, <?= \App\Core\Text::plural((int) $image['comments'], 'comment') ?></span>
        <?php if ($viewerId !== null && !$sien): ?>
            <?php if ((int) $image['reported'] === 1): ?>
        <span class="reported">Reported</span>
            <?php else: ?>
        <form method="post" action="/gallery/report">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <button type="submit" class="button-quiet">Report</button>
        </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($sien): ?>
        <form method="post" action="/photo/delete" class="delete-form">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <input type="hidden" name="back" value="gallery">
            <button type="submit">Delete</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($commentaires[$id])): ?>
    <ul class="comments">
        <?php foreach ($commentaires[$id] as $commentaire): ?>
        <li>
            <span class="author<?= $commentaire['username'] === null ? ' author-gone' : '' ?>"><?=
                htmlspecialchars((string) ($commentaire['username'] ?? 'Deleted account')) ?></span>
            <span class="when"><?= htmlspecialchars($quand((string) $commentaire['created_at'])) ?></span>
            <p><?= nl2br(htmlspecialchars((string) $commentaire['comment'])) ?></p>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($viewerId !== null && !$sien): ?>
    <form class="form-block" method="post" action="/gallery/comment">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="page" value="<?= $page ?>">
        <p class="field">
            <label for="comment-<?= $id ?>">Comment</label>
            <textarea id="comment-<?= $id ?>" name="comment" rows="2"
                      maxlength="<?= $maxComment ?>"></textarea>
        </p>
        <p class="actions"><button type="submit">Post</button></p>
    </form>
    <?php elseif ($viewerId === null): ?>
    <p class="note"><a href="/login">Log in</a> to like and comment.</p>
    <?php endif; ?>
</article>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
<nav class="pagination">
    <?php if ($page > 1): ?>
    <a href="/gallery?page=<?= $page - 1 ?>">Previous</a>
    <?php endif; ?>
    <span><?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?>
    <a href="/gallery?page=<?= $page + 1 ?>">Next</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
