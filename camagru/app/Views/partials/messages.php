<?php
/** @var string|null $notice */
/** @var string[]|null $errors */
?>
<?php if (!empty($notice)): ?>
    <p class="avis"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
    <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>
