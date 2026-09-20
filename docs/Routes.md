# Routes

## 1 : Ce qui déclenche une route

Une route est atteinte chaque fois que le navigateur émet une requête vers
l'application. Les émetteurs, tous présents dans le projet :

| Émetteur | Méthode | Exemple |
|----------|---------|---------|
| Saisie d'URL, favori, rechargement | `GET` | `http://localhost:8080/gallery` |
| Lien `<a href>` | `GET` | le menu, `/login`, `/register` |
| Soumission de formulaire | `POST` | `/gallery/like`, `/photobooth/capture` |
| Redirection renvoyée par le serveur | `GET` | le `Location:` qui suit chaque POST |
| Ressource embarquée dans la page | `GET` | `<img src="/photo?id=12">`, `<img src="/avatar?id=3">` |
| Appel JavaScript | `GET` | `fetch('/gallery?page=2')` du scroll infini |
| Lien reçu par mail | `GET` | `/verify?token=…`, `/reset-password?token=…` |

Les trois dernières lignes sont celles qu'on oublie. Une balise `<img>` est une
requête HTTP complète : elle traverse le routeur, les filtres compris, et le
contrôleur répond avec des octets d'image au lieu d'une page.

Toutes ces requêtes n'arrivent pas au routeur. Apache sert directement ce qui
correspond à un fichier réel de `public/` :

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

`/css/base.css`, `/js/gallery.js`, `/images/perso1.png` et `/avatars/modele_01.png`
existent sur le disque : PHP n'est jamais démarré pour eux. `/photo?id=12` et
`/avatar?id=3` ne correspondent à aucun fichier : ils sont réécrits vers
`index.php` et deviennent des routes.

Le même chemin peut d'ailleurs basculer de l'un à l'autre : `/avatar?id=3` est
une route qui, pour un avatar de la galerie de modèles, répond par une
redirection vers `/avatars/modele_01.png`, servi ensuite par Apache sans passer
par PHP.

## 2 : Déclaration

`config/routes.php` retourne une fonction qui reçoit le routeur et remplit la
table :

```php
return static function (Router $router): void {
    $router->get('/gallery', [GalleryController::class, 'gallery']);
    $router->post('/gallery/like', [GalleryController::class, 'like']);
};
```

Une route associe une méthode HTTP et un chemin à un couple
`[classe, méthode]`. `GET /login` et `POST /login` sont deux routes distinctes,
vers deux méthodes distinctes : affichage du formulaire, traitement.

Le fichier importe chaque contrôleur en tête :

```php
use App\Controllers\GalleryController;
```

Sans cet import, `GalleryController::class` se résout dans le mauvais namespace.

Seuls `get()` et `post()` existent. Aucune route `PUT`, `PATCH` ou `DELETE` :
un formulaire HTML ne sait émettre que ces deux méthodes, et le projet n'expose
pas d'API.

## 3 : Chemins

Aucun paramètre dans le chemin. Un identifiant passe en requête (`/photo?id=12`)
ou dans le corps du POST, jamais en segment d'URL.

`normalize()` retire les barres de début et de fin avant l'enregistrement comme
avant la résolution :

```php
private function normalize(string $path): string
{
    return '/' . trim($path, '/');
}
```

`/gallery`, `/gallery/` et `gallery` désignent donc la même route. La chaîne de
requête ne fait pas partie du chemin : `index.php` la retire avant d'appeler le
routeur.

```php
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
```

La correspondance est une lecture de tableau, pas une expression régulière :
`$this->routes[$method][$path]`.

## 4 : Résolution

`Router::dispatch()` applique trois filtres avant d'instancier quoi que ce soit.

| Ordre | Filtre | Condition | Réponse |
|-------|--------|-----------|---------|
| 1 | CSRF | méthode `POST`, jeton absent ou invalide | 403 avec message |
| 2 | Authentification | route en `requireAuth`, `$_SESSION['user']` vide | redirection vers `/login` |
| 3 | Droits | route en `requireAdmin`, `is_admin` absent | 403 |

```php
if ($httpMethod === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
    (new ErrorController())->forbidden('Security token invalid or expired. Reload the page and try again.');
    return;
}

if (!empty($this->protectedRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user'])) {
    header('Location: /login');
    exit;
}
if (!empty($this->adminRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user']['is_admin'])) {
    (new ErrorController())->forbidden();
    return;
}

$action = $this->routes[$httpMethod][$normalizedPath] ?? null;

if ($action === null) {
    (new ErrorController())->notFound();
    return;
}

[$controller, $method] = $action;
(new $controller())->{$method}();
```

Les filtres passent avant la recherche de la route : une route protégée et une
route inexistante se distinguent, mais le jeton CSRF est exigé sur tout POST,
y compris vers un chemin qui n'existe pas.

Le contrôleur est instancié sans argument et la méthode appelée sans paramètre.
Tout ce dont elle a besoin vient de `$_GET`, `$_POST` et `$_SESSION`.

Un seul contrôleur est instancié par requête, celui que la table désigne. Il n'y
a ni chaîne de contrôleurs, ni pile de middlewares : les trois filtres sont des
conditions dans `dispatch()`, pas des objets traversés.

Les `use App\Controllers\…` en tête de `config/routes.php` ne chargent rien : un
`use` est une règle de résolution de nom, résolue à la compilation.
`[GalleryController::class, 'gallery']` est un couple de chaînes de caractères.
Le fichier du contrôleur n'est lu qu'au moment du `new $controller()`, par
l'autoloader, et seulement celui-là. Les onze autres contrôleurs du projet ne
sont jamais chargés pour cette requête.

## 5 : Protection

Trois tables distinctes, remplies par trois méthodes :

| Méthode | Table | Effet |
|---------|-------|-------|
| `get()` / `post()` | `routes` | déclare la cible |
| `requireAuth()` | `protectedRoutes` | exige une session ouverte |
| `requireAdmin()` | `adminRoutes` | exige `is_admin` |

```php
public function requireAuth(string $method, string $path): void
{
    $this->protectedRoutes[$method][$this->normalize($path)] = true;
}
```

Les deux protections se déclarent par méthode HTTP et par chemin, séparément de
la route :

```php
$router->requireAuth('GET', '/preferences');
$router->requireAuth('POST', '/preferences/account');
```

Protéger l'affichage ne protège pas l'envoi : une page en `GET` et son
traitement en `POST` demandent deux déclarations.

`requireAdmin` ne remplace pas `requireAuth`. Les routes d'administration
portent les deux, pour qu'un visiteur anonyme soit redirigé vers `/login` au
lieu de recevoir un 403 :

```php
$router->requireAuth('GET', '/admin');
$router->requireAdmin('GET', '/admin');
```

Une route non déclarée dans `protectedRoutes` est publique. L'oubli est
silencieux : rien ne signale qu'une route sensible n'a pas été protégée.

## 6 : Convention de nommage

| Forme | Usage | Exemple |
|-------|-------|---------|
| `/ressource` en `GET` | affichage d'une page | `/gallery`, `/profile` |
| `/ressource/action` en `POST` | action qui modifie l'état | `/gallery/like`, `/photo/delete` |
| `/ressource?id=` en `GET` | fichier ou élément servi par un contrôleur | `/photo?id=12` |

Une action en `POST` se termine par une redirection, jamais par un rendu de
vue : un rafraîchissement rejouerait la requête.

## 7 : Erreurs

`ErrorController` porte les deux réponses, avec le code HTTP et la vue :

```php
public function notFound(): void
{
    http_response_code(404);
    $this->view('errors/404', ['title' => 'Page not found']);
}

public function forbidden(string $reason = ''): void
{
    http_response_code(403);
    $this->view('errors/403', ['title' => 'Access denied', 'reason' => $reason]);
}
```

Le 403 accepte un motif, utilisé pour le jeton CSRF expiré ; l'accès refusé pour
droits insuffisants n'en donne aucun.

Une URL inconnue arrive jusqu'ici parce qu'Apache réécrit vers `index.php` tout
ce qui ne correspond ni à un fichier ni à un dossier existant : le routeur ne
trouve pas d'entrée, `ErrorController::notFound()` répond.

## 8 : Ajouter une route

1. Importer le contrôleur en tête de `config/routes.php`.
2. Déclarer la route avec `get()` ou `post()`.
3. Déclarer `requireAuth` si la route n'est pas publique, sur la méthode et le
   chemin exacts.
4. Déclarer `requireAdmin` en plus si elle est réservée aux administrateurs.
5. Pour un `POST`, placer `Csrf::field()` dans le formulaire et terminer la
   méthode par une redirection.

## Points d'attention

- Une route POST sans `Csrf::field()` dans son formulaire répond 403.
- `requireAuth('GET', ...)` ne couvre pas le `POST` du même chemin.
- Les chemins sont comparés à l'identique après normalisation : une faute de
  frappe produit un 404, pas une erreur au démarrage.
- Une action qui modifie l'état ne se déclare pas en `GET` : ces routes ne sont
  pas vérifiées par le filtre CSRF.
