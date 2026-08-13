<?php
?>
<section class="bloc">
    <h2>Identifiants</h2>
    <form class="formulaire" method="post" action="/preferences">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="pseudo">Pseudo</label>
            <input type="text" id="pseudo" name="pseudo" autocomplete="username">
        </p>
        <p class="champ">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" autocomplete="email">
        </p>
        <p class="champ">
            <label for="motdepasse">Nouveau mot de passe</label>
            <input type="password" id="motdepasse" name="motdepasse" autocomplete="new-password">
        </p>
        <p class="actions"><button type="submit">Enregistrer</button></p>
    </form>
</section>

<section class="bloc">
    <h2>Notifications</h2>
    <form class="formulaire" method="post" action="/preferences">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ champ-case">
            <input type="checkbox" id="avis-commentaire" name="avis-commentaire">
            <label for="avis-commentaire">Un message à chaque commentaire reçu</label>
        </p>
        <p class="actions"><button type="submit">Enregistrer</button></p>
    </form>
</section>
