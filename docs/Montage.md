# Montage

Trajet complet d'un montage, de la webcam au fichier servi dans la galerie.

## 1 : Vue d'ensemble

```
navigateur
    caméra ou fichier            source
    overlays posés à la souris   géométrie, en fractions de la scène
    │
    │  POST /photobooth/capture
    │  capture (data URL) ou file, layers (JSON), csrf_token
    ▼
PhotoboothController::capture
    ▼
Services/Montage
    layers()        valide la géométrie et les slugs
    fromDataUrl()   décode la capture
    fromUpload()    ou le fichier envoyé
    compose()       recadre, superpose, écrit le JPEG
    ▼
Models/Image::create()           une ligne, le nom de fichier
    ▼
storage/images/<32 hex>.jpg      hors DocumentRoot
    ▼
GET /photo?id=…&v=…  →  PhotoController::show
```

Le navigateur n'envoie jamais d'image composée : il envoie une source et des
coordonnées. La superposition est faite par GD, côté serveur.

## 2 : Le catalogue d'overlays

`config/settings.php` liste les slugs et leurs libellés :

```php
'filters' => [
    'cat-ears'      => 'Cat ears',
    'heart-glasses' => 'Heart glasses',
    // ...
],
```

`Services/Overlays::catalogue()` croise cette liste avec les fichiers présents
dans `public/filtres/<slug>.png`. Une entrée sans fichier est écartée : le
catalogue affiché ne contient que des overlays réellement utilisables.

```php
foreach ((array) Settings::get('photobooth.' . $cle, []) as $slug => $label) {
    if ($this->path((string) $slug) === null) {
        continue;
    }
    $entrees[] = ['slug' => $slug, 'label' => $label,
                  'url'  => '/' . $dossier . '/' . $slug . '.png?v=' . $version];
}
```

`path()` est aussi le contrôle d'entrée côté serveur : un slug absent du
catalogue rend `null`, et le montage est refusé.

Les PNG sont produits par `scripts/filtres.py`, qui trace les motifs en SVG puis
les rend en PNG avec canal alpha. GD ne lit pas le SVG, d'où la double sortie.

## 3 : La scène

La zone de composition reprend le format du montage serveur :

```php
<div class="scene" id="scene" style="aspect-ratio: <?= $largeur ?> / <?= $hauteur ?>">
    <video id="stream" playsinline muted></video>
    <img id="preview" alt="Montage in progress" hidden>
    <div class="pieces" id="pieces"></div>
</div>
```

`$largeur` et `$hauteur` viennent de `photobooth.width` et `photobooth.height`.
Ce qui est cadré à l'écran est donc ce qui sera produit : les positions relevées
en fractions de la scène restent valables une fois appliquées aux 800 × 600 du
serveur.

Le `<video>` affiche le direct, l'`<img>` prend sa place dès qu'une source est
figée, et `.pieces` porte les overlays posés.

## 4 : Poser un overlay

Chaque overlay posé est une entrée du tableau `pieces` :

```js
// {slug, x, y, w, el, choix}: x and y locate the centre, w the width,
// all as fractions of the scene
const pieces = [];
```

Un clic sur une vignette ajoute la pièce, un second clic la retire. Le
déplacement et le redimensionnement se font à la souris, et les valeurs sont
bornées à la prise :

```js
piece.x = borne(prise.x + (evenement.clientX - boite.left) / boite.width, 0, 1);
piece.w = borne(piece.w * facteur, LARGEUR_MIN, LARGEUR_MAX);
```

Les coordonnées sont stockées en fractions et non en pixels : la scène change de
taille avec la fenêtre, les proportions non.

À chaque modification, `synchroniser()` réécrit le champ caché `layers` et
recalcule l'état des boutons :

```js
champCalques.value = JSON.stringify(pieces.map((piece) => ({
    o: piece.slug,
    x: Number(piece.x.toFixed(4)),
    y: Number(piece.y.toFixed(4)),
    w: Number(piece.w.toFixed(4)),
})));

prendre.disabled = !cameraPrete || pieces.length === 0;
enregistrer.disabled = pieces.length === 0 || !source;
```

Le bouton de capture reste inactif tant qu'aucun overlay n'est posé, et
l'enregistrement tant qu'il n'y a pas de source.

## 5 : La source

Deux chemins, exclusifs : le second champ est vidé dès que le premier est
rempli.

Capture webcam, par un canvas hors écran :

```js
const toile = document.createElement('canvas');
toile.width = flux.videoWidth;
toile.height = flux.videoHeight;

// the preview is mirrored: the shot has to match what was aimed at
const pinceau = toile.getContext('2d');
pinceau.translate(toile.width, 0);
pinceau.scale(-1, 1);
pinceau.drawImage(flux, 0, 0);

champCapture.value = toile.toDataURL('image/jpeg', 0.9);
```

L'aperçu est retourné en CSS pour se comporter comme un miroir ; le canvas
applique la même inversion, sans quoi la photo sortirait à l'envers de ce qui
était visé.

Fichier, quand il n'y a pas de caméra ou que l'utilisateur préfère une image :
le champ `file` est envoyé tel quel, et `getUserMedia` absent ou refusé
bascule l'interface sur ce mode.

```js
const demande = navigator.mediaDevices?.getUserMedia({ ... });
if (demande === undefined) {
    sansCamera();
}
```

`getUserMedia` n'existe que dans un contexte sécurisé, HTTPS ou `localhost`.

## 6 : Ce que le formulaire envoie

```php
<form id="montage" method="post" action="/photobooth/capture"
      enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="capture" id="capture">
    <input type="hidden" name="layers" id="layers">
    <input type="file" id="file" name="file" accept="image/jpeg,image/png,image/gif">
</form>
```

| Champ | Contenu |
|-------|---------|
| `capture` | data URL JPEG de la capture webcam, ou vide |
| `file` | fichier choisi, ou vide |
| `layers` | JSON des overlays : slug, centre, largeur |
| `csrf_token` | jeton, exigé par le routeur sur tout POST |

`enctype="multipart/form-data"` est imposé par le champ fichier. Les champs
texte continuent d'alimenter `$_POST`.

## 7 : Réception

```php
public function capture(): void
{
    $montage = new Montage();

    try {
        $calques = $montage->layers((string) ($_POST['layers'] ?? ''));
        $source  = $this->source($montage);

        $nom = $montage->compose($source, $calques);
        (new Image())->create($this->userId(), $nom);

        Flash::notice('Montage saved.');
    } catch (RuntimeException $e) {
        error_log('Montage rejected: ' . $e->getMessage());
        Flash::errors([$e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Montage failed: ' . $e->getMessage());
        Flash::errors(['Montage failed.']);
    }

    $this->redirect('/photobooth');
}
```

La géométrie est validée avant que la source soit décodée : un JSON invalide
évite de charger une image en mémoire pour rien.

Deux niveaux d'erreur. `RuntimeException` porte un message destiné à
l'utilisateur, levé par les validations du service. Tout le reste est renvoyé
sous un message générique, le détail partant dans le log. Dans les deux cas, la
réponse est une redirection : un rafraîchissement ne rejoue pas la capture.

Le choix de la source est explicite, fichier prioritaire :

```php
$fichier = $_FILES['file'] ?? null;
if (is_array($fichier) && (int) $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
    return $montage->fromUpload($fichier);
}

$capture = (string) ($_POST['capture'] ?? '');
if ($capture === '') {
    throw new RuntimeException('Take a shot or pick an image first.');
}

return $montage->fromDataUrl($capture);
```

## 8 : Validation de la géométrie

```php
public function layers(string $json): array
{
    $brut = json_decode($json, true);
    if (!is_array($brut) || $brut === []) {
        throw new RuntimeException('Pick at least one overlay.');
    }
    if (count($brut) > (int) Settings::get('photobooth.max_layers', 8)) {
        throw new RuntimeException('Too many overlays on this montage.');
    }

    foreach ($brut as $calque) {
        if (!is_array($calque) || $this->overlays->path((string) ($calque['o'] ?? '')) === null) {
            throw new RuntimeException('Unknown overlay.');
        }
        $calques[] = [
            'slug' => (string) $calque['o'],
            'x'    => $this->borne((float) ($calque['x'] ?? .5), 0, 1),
            'y'    => $this->borne((float) ($calque['y'] ?? .5), 0, 1),
            'w'    => $this->borne((float) ($calque['w'] ?? .5), $minimum, $maximum),
        ];
    }

    return $calques;
}
```

Rien de ce qui vient du navigateur n'est cru : le JSON peut être forgé sans
passer par la page. Le slug doit exister dans le catalogue, le nombre de calques
est plafonné, et les coordonnées sont ramenées dans leurs bornes plutôt que
refusées.

## 9 : Décodage de la source

La capture doit être une data URL d'image :

```php
if (!preg_match('#^data:image/(?:jpeg|png);base64,#', $dataUrl, $entete)) {
    throw new RuntimeException('Unreadable capture.');
}
$binaire = base64_decode(substr($dataUrl, strlen($entete[0])), true);
```

Le troisième argument de `base64_decode` à `true` refuse les caractères hors
alphabet au lieu de les ignorer.

Pour un envoi de fichier, le code d'erreur est lu avant tout, et
`is_uploaded_file()` confirme que le chemin vient bien d'un envoi HTTP :

```php
if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE) {
    throw new RuntimeException('Image too large: ' . ini_get('upload_max_filesize') . ' at most.');
}
if ($erreur !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($fichier['tmp_name'] ?? ''))) {
    throw new RuntimeException('Upload failed.');
}
```

Les deux chemins convergent sur `decode()`, qui applique quatre contrôles avant
de laisser GD travailler :

| Contrôle | Valeur |
|----------|--------|
| Taille des données | `photobooth.max_source`, 6 Mio |
| Type réel | `getimagesizefromstring()`, comparé à `photobooth.allowed_mime` |
| Résolution | `MAX_PIXELS`, 16 millions de pixels |
| Décodage | `imagecreatefromstring()`, qui échoue sur un contenu corrompu |

Le type vient de la lecture des octets, jamais de l'en-tête déclaré par le
client. Le plafond de pixels protège la mémoire : GD travaille en 4 octets par
pixel, indépendamment du poids du fichier compressé.

## 10 : Composition

Le cadrage remplit le format cible sans déformer, en rognant le côté qui
dépasse, centré :

```php
$prise = [0, 0, $sourceLargeur, $sourceHauteur];
if ($sourceLargeur / $sourceHauteur > $ratio) {
    $prise[2] = (int) round($sourceHauteur * $ratio);
    $prise[0] = intdiv($sourceLargeur - $prise[2], 2);
} else {
    $prise[3] = (int) round($sourceLargeur / $ratio);
    $prise[1] = intdiv($sourceHauteur - $prise[3], 2);
}

$montage = imagecreatetruecolor($largeur, $hauteur);
imagecopyresampled($montage, $source, 0, 0, $prise[0], $prise[1],
    $largeur, $hauteur, $prise[2], $prise[3]);
```

C'est l'équivalent GD de `object-fit: cover`. Les portraits de `scripts/`
sont cadrés au même ratio pour que ce recadrage ne retire rien.

Chaque overlay est ensuite redimensionné puis collé, centré sur sa coordonnée :

```php
imagealphablending($montage, true);

$cible = max(1, (int) round($calque['w'] * $largeur));
$echelle = $cible / imagesx($overlay);
$hauteurCible = max(1, (int) round(imagesy($overlay) * $echelle));

imagecopyresampled(
    $montage, $overlay,
    (int) round($calque['x'] * $largeur - $cible / 2),
    (int) round($calque['y'] * $hauteur - $hauteurCible / 2),
    0, 0,
    $cible, $hauteurCible,
    imagesx($overlay), imagesy($overlay)
);
```

`imagealphablending($montage, true)` fait composer les pixels transparents avec
le fond au lieu de les écraser. Sans lui, l'overlay poserait un rectangle opaque.

La hauteur est déduite de la largeur : les proportions de l'overlay sont
conservées, seul `w` circule.

Les ressources GD sont libérées au fur et à mesure (`imagedestroy`), la source
juste après le recadrage.

## 11 : Écriture

```php
$nom = bin2hex(random_bytes(16)) . '.jpg';
$ecrit = imagejpeg($montage, $dossier . $nom, (int) Settings::get('photobooth.quality', 85));
```

Le nom est aléatoire, 32 caractères hexadécimaux : rien du nom d'origine n'est
réutilisé, et deux envois simultanés ne peuvent pas se recouvrir.

Le fichier va dans `storage/images/`, hors `DocumentRoot`, donc inatteignable
par URL directe. La base ne garde que le nom :

```php
(new Image())->create($this->userId(), $nom);
```

## 12 : Restitution

L'URL d'affichage est construite par le service :

```php
public static function url(array $image): string
{
    return '/photo?id=' . (int) $image['id']
        . '&v=' . rawurlencode(substr((string) $image['filename'], 0, 12));
}
```

Le `v=` reprend le début du nom de fichier. Les identifiants repartent de 1
après une réinitialisation de la base, alors que les navigateurs gardent les
images une semaine : sans ce paramètre, un ancien montage s'afficherait à la
place d'un nouveau portant le même `id`.

`PhotoController::show` sert le fichier :

```php
header('Content-Type: image/jpeg');
header('Content-Length: ' . (string) filesize($fichier));
header('Cache-Control: public, max-age=604800, immutable');
readfile($fichier);
```

`Montage::path()` refuse tout nom contenant un chemin, ce qui ferme la
traversée de répertoire :

```php
if ($filename === '' || basename($filename) !== $filename) {
    return null;
}
```

## 13 : Suppression

`Image::delete($id, $userId)` filtre sur le propriétaire dans la requête, en
transaction, et renvoie le nom du fichier supprimé, ou `null` si la ligne
n'appartient pas au demandeur. Le contrôleur passe ensuite le nom à
`Montage::remove()`, qui efface le fichier.

L'ordre compte : la ligne d'abord, le fichier ensuite. Un échec après la
suppression de la ligne laisse un fichier orphelin ; l'inverse laisserait une
ligne pointant vers un fichier absent, visible comme une image cassée dans la
galerie.

## Garde-fous

| Point | Contrôle |
|-------|----------|
| Accès | `requireAuth` sur `GET /photobooth` et `POST /photobooth/capture` |
| Requête forgée | jeton CSRF vérifié par le routeur |
| Overlay inconnu | `Overlays::path()` rend `null`, montage refusé |
| Nombre de calques | `photobooth.max_layers` |
| Échelle, position | bornées par `min_scale`, `max_scale`, et `[0,1]` |
| Poids de la source | `photobooth.max_source`, plus `upload_max_filesize` |
| Type de la source | lu dans les octets, comparé à `allowed_mime` |
| Résolution | `MAX_PIXELS` |
| Nom de fichier | généré serveur, 16 octets aléatoires |
| Emplacement | `storage/`, hors `DocumentRoot` |
| Lecture d'un montage | `basename()` comparé au nom reçu |
| Suppression | propriétaire filtré dans le `WHERE` |
