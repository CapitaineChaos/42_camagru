# Ajouter une route

Une route associe une méthode HTTP et un chemin à une méthode de contrôleur.
Toutes les routes sont déclarées dans `camagru/config/routes.php`, qui reçoit le
routeur et remplit sa table. Un chemin ne contient aucun paramètre : un
identifiant passe dans la chaîne de requête (`/photo?id=12`) ou dans le corps
d'un POST.

## 1 : Méthodes HTTP

| Méthode | Sens | Corps |
|---------|------|-------|
| `GET` | Lit une ressource. | non |
| `POST` | Soumet des données à la ressource. | oui |
| `HEAD` | Comme `GET`, sans corps de réponse. | non |
| `PUT` | Remplace la ressource par le corps envoyé. | oui |
| `PATCH` | Modifie une partie de la ressource. | oui |
| `DELETE` | Supprime la ressource. | facultatif |
| `OPTIONS` | Liste les méthodes acceptées par la ressource. | facultatif |
| `CONNECT` | Ouvre un tunnel vers le serveur cible. | non |
| `TRACE` | Renvoie la requête reçue. | non |

Méthodes sûres, sans effet sur l'état du serveur : `GET`, `HEAD`, `OPTIONS`,
`TRACE`. Méthodes idempotentes, dont la répétition produit le même effet qu'un
seul appel : les méthodes sûres, `PUT` et `DELETE`.

Le routeur fournit `get()` et `post()`. Une requête d'une autre méthode reçoit
un 404.

## 2 : Méthode de contrôleur

Les contrôleurs sont dans `camagru/app/Controllers/`, un fichier par classe, dans
l'espace de noms `App\Controllers`. L'autoloader déduit le fichier du nom de la
classe : `App\Controllers\GalleryController` est chargé depuis
`app/Controllers/GalleryController.php`.

La méthode appelée par la route est publique, ne prend aucun argument et ne
renvoie rien. Elle lit ses entrées dans `$_GET`, `$_POST` et `$_SESSION`, et
termine par l'une des trois réponses de `Core\Controller` :

| Méthode | Réponse |
|---------|---------|
| `$this->view('nom', [...])` | rend `app/Views/nom.php` dans le layout ; les clés du tableau deviennent des variables de la vue |
| `$this->redirect('/chemin')` | en-tête `Location` (code 302), puis fin du script |
| `$this->json([...])` | corps JSON, avec `Content-Type: application/json` |

Exemple, le « j'aime » de la galerie :

```php
// app/Controllers/GalleryController.php
public function like(): void
{
    $id = (int) ($_POST['id'] ?? 0);

    if ($this->cible($id) !== null) {
        (new Like())->toggle($id, (int) $_SESSION['user']['id']);
    }

    $this->redirect($this->retour($id));
}
```

Un nouveau contrôleur hérite de `Controller` et se déclare `final` :

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ExempleController extends Controller
{
    public function index(): void
    {
        $this->view('exemple', ['title' => 'Exemple']);
    }
}
```

## 3 : Déclaration

Dans `config/routes.php`, importer le contrôleur en tête de fichier. Sans cet
import, `ExempleController::class` désigne `\ExempleController`, que
l'autoloader ne charge pas : l'appel de la route échoue en erreur 500.

```php
use App\Controllers\ExempleController;
```

Puis déclarer la route dans la fonction :

```php
$router->get('/exemple', [ExempleController::class, 'index']);
$router->post('/exemple/envoyer', [ExempleController::class, 'envoyer']);
```

`GET /login` et `POST /login` sont deux routes distinctes, qui peuvent viser deux
méthodes différentes. Les barres obliques de début et de fin sont retirées avant
l'enregistrement et avant la recherche : `/exemple`, `/exemple/` et `exemple`
désignent la même route.

## 4 : Protection

Une route est publique sauf si sa déclaration porte un niveau d'accès en
troisième argument :

```php
$router->get('/exemple', [ExempleController::class, 'exemple'], Router::AUTH);
$router->post('/exemple/envoyer', [ExempleController::class, 'envoyer'], Router::AUTH);
```

| Niveau | Effet si la condition manque |
|--------|------------------------------|
| `Router::AUTH` | redirection vers `/login` quand aucune session n'est ouverte |
| `Router::ADMIN` | redirection vers `/login` sans session, réponse 403 sans le rang d'admin |

Protéger le `GET` d'une page ne protège pas le `POST` qui la traite : chaque
route porte son propre niveau.

## 5 : Route POST

Le routeur vérifie le jeton CSRF de toute requête POST avant d'appeler le
contrôleur ; un jeton absent ou invalide donne une réponse 403. Le formulaire
porte donc le champ du jeton :

```php
<form method="post" action="/gallery/like">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit">Like</button>
</form>
```

Une requête `fetch` envoie le jeton dans un corps au format formulaire
(`FormData`) ; un corps JSON laisse `$_POST['csrf_token']` vide et la requête est
refusée.

Une action en POST se termine par une redirection : un rafraîchissement de la
page affichée ensuite refait un `GET`, sans renvoyer le formulaire. Un message à
afficher après la redirection passe par `Flash::notice()` ou `Flash::errors()` ;
la méthode `GET` qui rend la page le récupère avec `Flash::pull()`.

Une action qui modifie des données ne se déclare jamais en `GET` : le contrôle
CSRF ne porte que sur les requêtes POST.

## 6 : Ordre de traitement

`Router::dispatch()` applique les contrôles dans cet ordre, avant d'instancier le
contrôleur :

| Ordre | Contrôle | Réponse en cas d'échec |
|-------|----------|------------------------|
| 1 | jeton CSRF, pour toute requête POST | 403 |
| 2 | route existante pour cette méthode et ce chemin | 404 |
| 3 | `Router::AUTH` ou `Router::ADMIN` : session ouverte | redirection vers `/login` |
| 4 | `Router::ADMIN` : rang d'admin | 403 |

Un POST vers un chemin inexistant reçoit donc un 403 s'il n'a pas de jeton
valide, et un 404 sinon.

## 7 : Prise en compte

Avec `make dev`, `app/` et `config/` sont montés depuis le dépôt : la route est
active dès l'enregistrement des fichiers. Avec `make up`, le code est copié dans
l'image, et la route n'existe qu'après un nouveau `make up`.
