# Ajouter une page au projet Camagru

Exemple avec la Galerie :

## 1 : Créer un nouveau contrôleur


Dans le dossier `app/Controllers`, créer un fichier `GalleryController.php` avec le contenu minimum suivant :

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

Le controlleur est une classe qui hérite de la classe `Controller`. Il est déclaré avec le mot-clé `final` pour indiquer qu'il ne peut pas être étendu. La méthode `gallery()` est publique et retourne `void`. Elle appelle la méthode héritée `view()`, qui charge la vue correspondante puis l'injecte dans le layout.

Le namespace `App\Controllers` est utilisé pour organiser les classes et éviter les conflits de noms. Il est important de respecter la structure des dossiers et des namespaces pour que l'autoloading fonctionne correctement.

## 2 : Créer une nouvelle vue

Dans le dossier `app/Views`, créer un fichier `gallery.php` avec le contenu d'exemple suivant :

```php
<h1>Gallery</h1>
```

### 3 : Ajouter les routes

Dans le fichier `config/routes.php`, ajouter les routes pour la galerie.

- Une route GET pour afficher la galerie :

```php
$router->get('/gallery', [GalleryController::class, 'gallery']);
```

- Une route POST pour gérer les actions de la galerie :

```php
$router->post('/gallery', [GalleryController::class, 'gallery']);
```

Une route est construite comme suit : `$router->méthode('/nom_de_la_route', [NomDuController::class, 'nom_de_la_methode']);`
