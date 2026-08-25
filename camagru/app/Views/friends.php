<?php
/** @var list<array<string, mixed>> $incoming */
/** @var list<array<string, mixed>> $friends */
/** @var list<array<string, mixed>> $outgoing */
/** @var list<array<string, mixed>> $resultats */
/** @var array<int, string> $etats */
/** @var string $recherche */

$ligne = static function (array $utilisateur, string $actions): string {
    return '<li class="friend">'
        . '<img class="friend-avatar" src="' . htmlspecialchars((string) $utilisateur['avatar_url']) . '"'
        . ' alt="" loading="lazy">'
        . '<span class="friend-name">' . htmlspecialchars((string) $utilisateur['username']) . '</span>'
        . '<span class="friend-actions">' . $actions . '</span>'
        . '</li>';
};

$bouton = static function (string $action, int $id, string $libelle, string $classe = ''): string {
    return '<form method="post" action="/friends/' . $action . '">'
        . \App\Core\Csrf::field()
        . '<input type="hidden" name="user" value="' . $id . '">'
        . '<button type="submit"' . ($classe === '' ? '' : ' class="' . $classe . '"') . '>'
        . $libelle . '</button>'
        . '</form>';
};
?>
<?php if (!empty($notice)): ?>
<p class="notice"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
<p class="error"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>

<section class="card">
    <h2>Add</h2>
    <form class="form-block form-inline" method="get" action="/friends">
        <p class="field">
            <label for="recherche">Username</label>
            <input type="search" id="recherche" name="q" value="<?= htmlspecialchars($recherche) ?>">
        </p>
        <p class="actions"><button type="submit">Search</button></p>
    </form>

    <?php if ($recherche !== ''): ?>
        <?php if ($resultats === []): ?>
    <p class="note">No account named like that.</p>
        <?php else: ?>
    <ul class="friends-list">
            <?php foreach ($resultats as $trouve): ?>
                <?php
                $id = (int) $trouve['id'];
                $actions = match ($etats[$id] ?? '') {
                    \App\Models\Friendship::FRIEND   => '<span class="friend-state">Friend</span>',
                    \App\Models\Friendship::OUTGOING => '<span class="friend-state">Asked</span>',
                    \App\Models\Friendship::INCOMING => $bouton('accept', $id, 'Accept'),
                    default                          => $bouton('request', $id, 'Add'),
                };
                ?>
        <?= $ligne($trouve, $actions) ?>
            <?php endforeach; ?>
    </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($incoming !== []): ?>
<section class="card">
    <h2>Pending requests <span class="count"><?= count($incoming) ?></span></h2>
    <ul class="friends-list">
        <?php foreach ($incoming as $demandeur): ?>
        <?= $ligne(
            $demandeur,
            $bouton('accept', (int) $demandeur['id'], 'Accept')
            . $bouton('remove', (int) $demandeur['id'], 'Decline', 'button-quiet')
        ) ?>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="card">
    <h2>Friends</h2>
    <?php if ($friends === []): ?>
    <p class="note">No friend yet.</p>
    <?php else: ?>
    <ul class="friends-list">
        <?php foreach ($friends as $ami): ?>
        <?= $ligne($ami, $bouton('remove', (int) $ami['id'], 'Remove', 'button-quiet')) ?>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php if ($outgoing !== []): ?>
<section class="card">
    <h2>Sent requests</h2>
    <ul class="friends-list">
        <?php foreach ($outgoing as $destinataire): ?>
        <?= $ligne($destinataire, $bouton('remove', (int) $destinataire['id'], 'Cancel', 'button-quiet')) ?>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
