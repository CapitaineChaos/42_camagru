<?php
/** @var string $reason */
?>
<section class="card card-narrow">
    <h2>Access denied</h2>
    <p class="note"><?= htmlspecialchars(($reason ?? '') !== '' ? $reason : 'You do not have permission to view this page.') ?></p>
    <p><a href="/">Back to home</a></p>
</section>
