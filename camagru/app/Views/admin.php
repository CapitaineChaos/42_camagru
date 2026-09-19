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
    <ul class="thumbs">
        <?php foreach ($signals as $signal): ?>
        <?php $id = (int) $signal['id']; ?>
        <li class="thumb">
            <img src="<?= htmlspecialchars(\App\Services\Montage::url($signal)) ?>" loading="lazy"
                 alt="Montage by <?= htmlspecialchars((string) $signal['username']) ?>">
            <div class="thumb-footer">
                <span class="counts"><?= htmlspecialchars((string) $signal['username']) ?>,
                    <?= \App\Core\Text::plural((int) $signal['reports'], 'report') ?></span>
            </div>
            <div class="thumb-footer">
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

<section class="card table-card">
    <h2>Users</h2>
    <table class="table-grid">
        <thead>
            <tr><th>Username</th><th>Email address</th><th>Member since</th>
                <th>Montages</th><th>Role</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($comptes as $compte): ?>
            <?php
            $id = (int) $compte['id'];
            $admin = (int) $compte['is_admin'] === 1;
            $suspendu = \App\Core\Pg::bool($compte['suspended']);
            ?>
            <tr<?= $suspendu ? ' class="suspended"' : '' ?>>
                <td data-label="Username"><?= htmlspecialchars((string) $compte['username']) ?></td>
                <td data-label="Email address"><?= htmlspecialchars((string) $compte['email']) ?></td>
                <td data-label="Member since"><?= htmlspecialchars($quand((string) $compte['created_at'])) ?></td>
                <td data-label="Montages"><?= (int) $compte['montages'] ?></td>
                <td data-label="Role"><?= $admin ? 'Admin' : 'Member' ?><?= $suspendu ? ', suspended' : '' ?></td>
                <td class="actions">
                    <?php if (!$admin && $id !== $moi): ?>
                    <form method="post" action="/admin/suspend">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="button-quiet"><?= $suspendu ? 'Restore' : 'Suspend' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
