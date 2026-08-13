<?php
?>
<section class="bloc">
    <h2>Ajouter</h2>
    <form class="formulaire formulaire-ligne" method="post" action="/friends">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="recherche">Pseudo</label>
            <input type="search" id="recherche" name="recherche">
        </p>
        <p class="actions"><button type="submit">Chercher</button></p>
    </form>
</section>

<section class="bloc">
    <h2>Demandes reçues</h2>
    <ul class="liste-amis">
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Accepter</button><button type="button">Refuser</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Accepter</button><button type="button">Refuser</button></span></li>
    </ul>
</section>

<section class="bloc">
    <h2>Amis</h2>
    <ul class="liste-amis">
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Retirer</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Retirer</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Retirer</button></span></li>
    </ul>
</section>
