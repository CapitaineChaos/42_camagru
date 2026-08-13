<?php
?>
<section class="bloc">
    <h2>Credentials</h2>
    <form class="formulaire" method="post" action="/preferences">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="pseudo">Username</label>
            <input type="text" id="pseudo" name="pseudo" autocomplete="username">
        </p>
        <p class="champ">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" autocomplete="email">
        </p>
        <p class="champ">
            <label for="motdepasse">New password</label>
            <input type="password" id="motdepasse" name="motdepasse" autocomplete="new-password">
        </p>
        <p class="actions"><button type="submit">Save</button></p>
    </form>
</section>

<section class="bloc">
    <h2>Notifications</h2>
    <form class="formulaire" method="post" action="/preferences">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ champ-case">
            <input type="checkbox" id="avis-commentaire" name="avis-commentaire">
            <label for="avis-commentaire">Email me on every comment received</label>
        </p>
        <p class="actions"><button type="submit">Save</button></p>
    </form>
</section>
