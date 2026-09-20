<?php
/** @var list<array<string, mixed>> $signals */
/** @var list<array<string, mixed>> $comptes */
/** @var int $moi */

$quand = static fn (string $horodatage): string
    => date('j M Y', (int) strtotime($horodatage));
?>
<?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>

<section class="card">
    <h2>Reported montages<?php if ($signals !== []): ?>
        <span class="count"><?= count($signals) ?></span><?php endif; ?></h2>
    <?php if ($signals === []): ?>
    <p class="note">Nothing reported.</p>
    <?php else: ?>
    <ul class="thumbs list-plain">
        <?php foreach ($signals as $signal): ?>
        <?php $id = (int) $signal['id']; ?>
        <li class="tile flex-vt">
            <img class="media" src="<?= htmlspecialchars(\App\Services\Montage::url($signal)) ?>" loading="lazy"
                 alt="Montage by <?= htmlspecialchars((string) $signal['username']) ?>">
            <div class="thumb-footer flex-hz between">
                <span class="counts"><?= htmlspecialchars((string) $signal['username']) ?>,
                    <?= \App\Core\Text::plural((int) $signal['reports'], 'report') ?></span>
            </div>
            <div class="thumb-footer flex-hz between">
                <form method="post" action="/admin/montage/delete" class="delete-form">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit">Delete</button>
                </form>
                <form method="post" action="/admin/report/dismiss">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="button-quiet">Dismiss</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Users</h2>
    <ul class="user-list flex-vt list-plain">
        <?php foreach ($comptes as $compte): ?>
        <?php
        $id = (int) $compte['id'];
        $admin = (int) $compte['is_admin'] === 1;
        $suspendu = \App\Core\Pg::bool($compte['suspended']);
        ?>
        <li class="tile flex-hz<?= $suspendu ? ' suspended' : '' ?>">
            <span class="uname"><?= htmlspecialchars((string) $compte['username']) ?></span>
            <span><?= htmlspecialchars((string) $compte['email']) ?></span>
            <span><?= htmlspecialchars($quand((string) $compte['created_at'])) ?></span>
            <span><?= (int) $compte['montages'] ?> montages</span>
            <span><?= $admin ? 'Admin' : 'Member' ?><?= $suspendu ? ', suspended' : '' ?></span>
            <?php if (!$admin && $id !== $moi): ?>
            <form method="post" action="/admin/suspend">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="button-quiet"><?= $suspendu ? 'Restore' : 'Suspend' ?></button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
