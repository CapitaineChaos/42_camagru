<?php
/** @var string|null $notice */
/** @var string[]|null $errors */
?>
<?php if (!empty($notice)): ?>
    <p class="notice"><?= htmlspecialchars($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors ?? [] as $erreur): ?>
    <p class="error"><?= htmlspecialchars($erreur) ?></p>
<?php endforeach; ?>
