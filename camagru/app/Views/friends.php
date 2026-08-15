<?php
?>
<section class="card">
    <h2>Add</h2>
    <form class="form-block form-inline" method="post" action="/friends">
        <?= \App\Core\Csrf::field() ?>
        <p class="field">
            <label for="recherche">Username</label>
            <input type="search" id="recherche" name="recherche">
        </p>
        <p class="actions"><button type="submit">Search</button></p>
    </form>
</section>

<section class="card">
    <h2>Pending requests</h2>
    <ul class="friends-list">
        <li class="friend"><span class="friend-avatar"></span><span class="friend-name"></span>
            <span class="friend-actions"><button type="button">Accept</button><button type="button">Decline</button></span></li>
        <li class="friend"><span class="friend-avatar"></span><span class="friend-name"></span>
            <span class="friend-actions"><button type="button">Accept</button><button type="button">Decline</button></span></li>
    </ul>
</section>

<section class="card">
    <h2>Friends</h2>
    <ul class="friends-list">
        <li class="friend"><span class="friend-avatar"></span><span class="friend-name"></span>
            <span class="friend-actions"><button type="button">Remove</button></span></li>
        <li class="friend"><span class="friend-avatar"></span><span class="friend-name"></span>
            <span class="friend-actions"><button type="button">Remove</button></span></li>
        <li class="friend"><span class="friend-avatar"></span><span class="friend-name"></span>
            <span class="friend-actions"><button type="button">Remove</button></span></li>
    </ul>
</section>
