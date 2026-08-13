<?php
/** @var string $reason */
?>
<section class="error-page">
    <p class="error-titre">Accès refusé</p>
    <p><?= htmlspecialchars(($reason ?? '') !== '' ? $reason : "Vous n'avez pas les droits nécessaires pour accéder à cette page.") ?></p>
    <a class="error-home" href="/">Retour à l'accueil</a>
</section>
