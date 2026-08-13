<?php
?>
<section class="bloc">
    <h2>Add</h2>
    <form class="formulaire formulaire-ligne" method="post" action="/friends">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="recherche">Username</label>
            <input type="search" id="recherche" name="recherche">
        </p>
        <p class="actions"><button type="submit">Search</button></p>
    </form>
</section>

<section class="bloc">
    <h2>Pending requests</h2>
    <ul class="liste-amis">
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Accept</button><button type="button">Decline</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Accept</button><button type="button">Decline</button></span></li>
    </ul>
</section>

<section class="bloc">
    <h2>Friends</h2>
    <ul class="liste-amis">
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Remove</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Remove</button></span></li>
        <li class="ami"><span class="ami-avatar"></span><span class="ami-nom"></span>
            <span class="ami-actions"><button type="button">Remove</button></span></li>
    </ul>
</section>
