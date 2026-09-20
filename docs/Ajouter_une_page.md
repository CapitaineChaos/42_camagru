# Ajouter une page au projet Camagru

Exemple avec la Galerie.

## 1 : Créer le contrôleur

Dans `app/Controllers`, un fichier `GalleryController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class GalleryController extends Controller
{
    public function gallery(): void
    {
        $this->view('gallery', ['title' => 'Gallery']);
    }
}
```

La classe hérite de `Controller` et est déclarée `final`. La méthode `gallery()`
appelle `view()`, qui rend la vue puis l'injecte dans le layout.

Le namespace `App\Controllers` doit correspondre au chemin `app/Controllers` :
l'autoloader de `public/index.php` déduit le fichier du nom de classe.

## 2 : Créer la vue

Dans `app/Views`, un fichier `gallery.php` :

```php
<h1>Gallery</h1>
```

La vue ne contient ni `<html>` ni `<head>` : `layout.php` les fournit.

Les données arrivent en variables, depuis le tableau passé à `view()` :
`['images' => $liste]` devient `$images`. Toute valeur issue de la base ou de
l'utilisateur est échappée :

```php
<p><?= htmlspecialchars((string) $image['filename']) ?></p>
```

## 3 : Ajouter la route

Dans `config/routes.php`, importer le contrôleur en haut du fichier :

```php
use App\Controllers\GalleryController;
```

Puis déclarer la route :

```php
$router->get('/gallery', [GalleryController::class, 'gallery']);
```

Forme générale : `$router->méthode('/chemin', [Controleur::class, 'methode']);`

Une méthode HTTP et un chemin par route. `GET /login` et `POST /login` sont deux
routes distinctes, vers deux méthodes distinctes.

## 4 : Protéger la route

Dans le même fichier, après les déclarations :

```php
$router->requireAuth('GET', '/gallery');        // connexion exigée
$router->requireAdmin('GET', '/admin');         // droit admin exigé
```

Sans connexion, `requireAuth` redirige vers `/login` ; sans `is_admin`,
`requireAdmin` répond 403.

## 5 : Entrée de menu

Dans `app/Views/layout.php`, ajouter la ligne au tableau `$liens` :

```php
$liens[] = ['/gallery', 'Gallery', 'gallery'];
```

Format : chemin, libellé (sert de texte alternatif), slug du lettrage. Les
entrées réservées aux connectés vont dans la branche `if (!empty($_SESSION['user']))`.

Le slug correspond à deux fichiers SVG, le menu latéral des pages internes et le
menu large de l'accueil :

```
public/images/elements/menu/gallery.svg
public/images/elements/accueil/gallery.svg
```

Sans ces fichiers, l'entrée s'affiche comme une image cassée.

## 6 : Titre de page

Toujours dans `layout.php`, tableau `$titres`, indexé par nom de vue :

```php
'gallery' => ['gallery', 'Gallery'],
```

Premier élément : le fichier `public/images/elements/titres/gallery.svg`, injecté
en ligne par `Svg::inline()`. Second : le libellé.

Une vue absente de `$titres` s'affiche sans titre. L'accueil est dans ce cas,
volontairement, puisqu'il porte le logo.

## 7 : Feuille de style propre à la page

Les feuilles communes sont chargées partout. Une feuille spécifique se déclare
dans `$specifiques`, toujours dans `layout.php` :

```php
$specifiques = [
    'home' => 'home',
    'gallery' => 'gallery',
    // ...
];
```

Clé : le nom de la vue. Valeur : le fichier `public/css/<valeur>.css`.

Après modification d'un CSS, d'un JS ou d'un SVG, incrémenter
`assets.version` dans `config/settings.php` : la valeur est concaténée en `?v=`
sur ces URL et force le rechargement côté navigateur.

## 8 : Traiter un formulaire

Une action qui modifie l'état se fait en POST, sur un chemin et une méthode
dédiés :

```php
// config/routes.php
$router->post('/gallery/like', [GalleryController::class, 'like']);
$router->requireAuth('POST', '/gallery/like');
```

Le formulaire porte le jeton CSRF, sinon le routeur répond 403 avant d'atteindre
le contrôleur :

```php
<form method="post" action="/gallery/like">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
    <button type="submit">Like</button>
</form>
```

La méthode lit l'entrée, délègue au modèle, et se termine par une redirection.
Aucune vue n'est rendue sur un POST : un rafraîchissement rejouerait l'action.

```php
public function like(): void
{
    $id = (int) ($_POST['id'] ?? 0);

    if ($this->cible($id) !== null) {
        (new Like())->toggle($id, (int) $_SESSION['user']['id']);
    }

    $this->redirect('/gallery');
}
```

## 9 : Messages après redirection

Un message posé avant la redirection survit à celle-ci :

```php
Flash::notice('Montage reported. An admin will look at it.');
Flash::errors(['Comment is too long.']);
```

La méthode GET les récupère et les passe à la vue :

```php
$this->view('gallery', ['title' => 'Gallery'] + Flash::pull());
```

Affichage dans la vue, par le partiel commun :

```php
<?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>
```

Il lit `$notice` et `$errors`, et les échappe.

## Récapitulatif

| Étape | Fichier |
|-------|---------|
| Contrôleur | `app/Controllers/XxxController.php` |
| Vue | `app/Views/xxx.php` |
| Route + `use` | `config/routes.php` |
| Protection | `config/routes.php` (`requireAuth`, `requireAdmin`) |
| Entrée de menu | `app/Views/layout.php` (`$liens`) + 2 SVG de lettrage |
| Titre | `app/Views/layout.php` (`$titres`) + 1 SVG de titre |
| CSS dédié | `app/Views/layout.php` (`$specifiques`) + `public/css/xxx.css` |
| Cache navigateur | `config/settings.php` (`assets.version`) |
