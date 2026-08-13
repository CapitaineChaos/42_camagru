<?php
?>
<section class="bloc">
    <h2>Capture</h2>
    <div class="capture">
        <div class="scene"></div>
        <p class="actions"><button type="button">Take the shot</button></p>
    </div>
    <form class="formulaire formulaire-ligne" method="post" action="/photobooth" enctype="multipart/form-data">
        <?= \App\Core\Csrf::field() ?>
        <p class="champ">
            <label for="fichier">Image</label>
            <input type="file" id="fichier" name="fichier" accept="image/*">
        </p>
        <p class="actions"><button type="submit">Upload</button></p>
    </form>
</section>

<section class="bloc">
    <h2>Overlays</h2>
    <ul class="calques">
        <li class="calque"></li>
        <li class="calque"></li>
        <li class="calque"></li>
        <li class="calque"></li>
        <li class="calque"></li>
    </ul>
</section>

<section class="bloc">
    <h2>Montages</h2>
    <ul class="vignettes">
        <li class="vignette"></li>
        <li class="vignette"></li>
        <li class="vignette"></li>
        <li class="vignette"></li>
    </ul>
</section>
