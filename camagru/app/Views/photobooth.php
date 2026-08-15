<?php
/** @var list<array{titre: string, entrees: list<array{slug: string, label: string, url: string}>}> $overlays */
/** @var list<array<string, mixed>> $montages */
/** @var int $largeur */
/** @var int $hauteur */
?>
<?php if (!empty($notice)): ?>
<p class="notice"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
<p class="error"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>

<section class="card">
    <h2>Capture</h2>
    <form class="montage" id="montage" method="post" action="/photobooth/capture"
          enctype="multipart/form-data">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="capture" id="capture">
        <input type="hidden" name="layers" id="layers">
        <div class="capture">
            <div class="scene" id="scene" style="aspect-ratio: <?= $largeur ?> / <?= $hauteur ?>">
                <video id="stream" playsinline muted></video>
                <img id="preview" alt="Montage in progress" hidden>
                <div class="pieces" id="pieces"></div>
                <p class="scene-status" id="status" hidden></p>
            </div>
            <p class="actions">
                <button type="button" id="shoot" disabled>Take the shot</button>
                <button type="button" id="retake" hidden>Retake</button>
                <button type="submit" id="save" disabled>Save</button>
            </p>
            <p class="field field-file">
                <label for="file">Image</label>
                <input type="file" id="file" name="file" accept="image/jpeg,image/png,image/gif">
            </p>
        </div>
    </form>
</section>

<?php foreach ($overlays as $famille): ?>
<section class="card">
    <h2><?= htmlspecialchars($famille['titre']) ?></h2>
    <ul class="filters">
        <?php foreach ($famille['entrees'] as $overlay): ?>
        <li class="filter">
            <button type="button" class="filter-choice" data-overlay="<?= htmlspecialchars($overlay['slug']) ?>"
                    data-url="<?= htmlspecialchars($overlay['url']) ?>">
                <img src="<?= htmlspecialchars($overlay['url']) ?>" alt="">
                <span><?= htmlspecialchars($overlay['label']) ?></span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>

<section class="card">
    <h2>Montages</h2>
    <?php if ($montages === []): ?>
    <p class="note">No montage yet.</p>
    <?php else: ?>
    <ul class="thumbs">
        <?php foreach ($montages as $image): ?>
        <li class="thumb">
            <img src="/photo?id=<?= (int) $image['id'] ?>" alt="Montage of <?= htmlspecialchars(date('j M Y', strtotime((string) $image['created_at']))) ?>" loading="lazy">
            <div class="thumb-footer">
                <span class="counts"><?= \App\Core\Text::plural((int) $image['likes'], 'like') ?>, <?= \App\Core\Text::plural((int) $image['comments'], 'comment') ?></span>
                <form method="post" action="/photo/delete" class="delete-form">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
