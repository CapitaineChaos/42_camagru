<?php
/** @var string $reason */
?>
<section class="error-page">
    <p class="error-titre">Access denied</p>
    <p><?= htmlspecialchars(($reason ?? '') !== '' ? $reason : 'You do not have permission to view this page.') ?></p>
    <a class="error-home" href="/">Back to home</a>
</section>
