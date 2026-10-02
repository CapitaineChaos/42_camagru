# Routes

## 1 : Ce qui déclenche une route

Une route est atteinte chaque fois que le navigateur émet une requête vers
l'application. Émetteurs :

| Émetteur | Méthode |
|----------|---------|
| Saisie d'URL, favori, rechargement | `GET` |
| Lien `<a href>` | `GET` |
| Soumission de formulaire | `GET` ou `POST`, selon l'attribut `method` |
| Redirection renvoyée par le serveur | `GET` |
| Ressource embarquée dans la page (`<img src>`) | `GET` |
| Appel JavaScript (`fetch()`) | `GET` ou `POST`, selon l'option `method` |
| Lien reçu par mail | `GET` |

Une balise `<img>` vers une route produit une requête HTTP complète : elle
traverse le routeur et ses filtres, et le contrôleur répond par les octets de
l'image.

Seules les requêtes sans fichier correspondant atteignent le routeur. Apache
sert directement les fichiers réels de `public/` :

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

`/css/base.css`, `/js/gallery.js`, `/images/fond_0.png` et `/avatars/modele_01.png`
existent sur le disque : PHP n'intervient pas. `/photo?id=12` et
`/avatar?id=3` ne correspondent à aucun fichier : ils sont réécrits vers
`index.php` et deviennent des routes.

Une route peut renvoyer vers un fichier statique : pour un avatar de la galerie
de modèles, la route `/avatar?id=3` répond par une redirection vers
`/avatars/modele_01.png`, que sert ensuite Apache sans passer par PHP.

## 2 : Déclaration

`config/routes.php` retourne une fonction qui reçoit le routeur et remplit la
table :

```php
return static function (Router $router): void {
    $router->get('/xxx', [XxxController::class, 'xxx']);
    $router->post('/xxx/action', [XxxController::class, 'action']);
};
```

Une route associe une méthode HTTP et un chemin à un couple
`[classe, méthode]`. `GET /login` et `POST /login` sont deux routes distinctes,
vers deux méthodes distinctes : affichage du formulaire, traitement.

Le fichier importe chaque contrôleur en tête :

```php
use App\Controllers\XxxController;
```

Sans cet import, `XxxController::class` se résout dans le mauvais namespace.

Seuls `get()` et `post()` existent. Aucune route `PUT`, `PATCH` ou `DELETE` :
un formulaire HTML ne sait émettre que ces deux méthodes, et le projet n'expose
pas d'API.

## 3 : Chemins

Aucun paramètre dans le chemin. Un identifiant passe en requête (`/photo?id=12`)
ou dans le corps du POST.

`normalize()` retire les barres de début et de fin avant l'enregistrement comme
avant la résolution :

```php
private function normalize(string $path): string
{
    return '/' . trim($path, '/');
}
```

`/xxx`, `/xxx/` et `xxx` désignent donc la même route. La chaîne de
requête ne fait pas partie du chemin : `index.php` la retire avant d'appeler le
routeur.

```php
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
```

La correspondance est une lecture de tableau, sans expression régulière :
`$this->routes[$method][$path]`.

## 4 : Résolution

`Router::dispatch()` applique quatre contrôles avant d'instancier quoi que ce
soit.

| Ordre | Contrôle | Condition | Réponse |
|-------|----------|-----------|---------|
| 1 | CSRF | méthode `POST`, jeton absent ou invalide | 403 avec message |
| 2 | Existence | aucune route pour cette méthode et ce chemin | 404 |
| 3 | Authentification | route en `AUTH` ou `ADMIN`, `$_SESSION['user']` vide | redirection vers `/login` |
| 4 | Droits | route en `ADMIN`, `is_admin` absent | 403 |

Le jeton CSRF est exigé sur tout POST, y compris vers un chemin qui n'existe
pas.

Le contrôleur est instancié sans argument et la méthode appelée sans paramètre.
Tout ce dont elle a besoin vient de `$_GET`, `$_POST` et `$_SESSION`.

Un seul contrôleur est instancié par requête, celui que la table désigne. Les
contrôles sont des conditions dans `dispatch()` ; il n'y a ni chaîne de
contrôleurs ni pile de middlewares.

Les `use App\Controllers\…` en tête de `config/routes.php` ne chargent rien : un
`use` est une règle de résolution de nom, résolue à la compilation.
`[XxxController::class, 'xxx']` est un couple de chaînes de caractères.
Le fichier du contrôleur n'est lu qu'au moment du `new $controller()`, par
l'autoloader, et seulement celui-là. Les autres contrôleurs ne
sont jamais chargés pour cette requête.

## 5 : Protection

Le niveau d'accès est le troisième argument de `get()` et `post()`, rangé avec
la cible dans la même entrée de la table :

| Niveau | Exige | Sinon |
|--------|-------|-------|
| `Router::OPEN` (par défaut) | rien | — |
| `Router::AUTH` | une session ouverte | redirection vers `/login` |
| `Router::ADMIN` | une session ouverte avec `is_admin` | `/login` sans session, 403 sans le rang |

```php
$router->get('/xxx', [XxxController::class, 'xxx'], Router::AUTH);
$router->post('/xxx/action', [XxxController::class, 'action'], Router::AUTH);
$router->get('/yyy', [YyyController::class, 'yyy'], Router::ADMIN);
```

Chaque route porte son propre niveau : protéger l'affichage en `GET` ne protège
pas le traitement en `POST` du même chemin.

`ADMIN` inclut `AUTH` : un visiteur anonyme sur une route d'administration est
redirigé vers `/login`.

Une route déclarée sans troisième argument est publique.

## 6 : Convention de nommage

| Forme | Usage | Exemple |
|-------|-------|---------|
| `/ressource` en `GET` | affichage d'une page | `/gallery`, `/profile` |
| `/ressource/action` en `POST` | action qui modifie l'état | `/gallery/like`, `/photo/delete` |
| `/ressource?id=` en `GET` | fichier ou élément servi par un contrôleur | `/photo?id=12` |

Une action en `POST` se termine par une redirection : après un rendu de vue, un
rafraîchissement rejouerait la requête.

## 7 : Erreurs

`ErrorController` porte les deux réponses d'erreur. `notFound()` pose le code
404 et rend `errors/404` ; `forbidden()` pose le code 403 et rend `errors/403`.
Un contrôleur qui doit refuser une requête appelle l'une de ces méthodes, puis
retourne :

```php
(new ErrorController())->notFound();
return;
```

Le 403 accepte un motif, utilisé pour le jeton CSRF expiré ; l'accès refusé pour
droits insuffisants n'en donne aucun.

Une URL inconnue arrive jusqu'ici parce qu'Apache réécrit vers `index.php` tout
ce qui ne correspond ni à un fichier ni à un dossier existant : le routeur ne
trouve pas d'entrée, `ErrorController::notFound()` répond.

## 8 : Ajouter une route

1. Importer le contrôleur en tête de `config/routes.php`.
2. Déclarer la route avec `get()` ou `post()`, avec `Router::AUTH` si elle
   n'est pas publique, `Router::ADMIN` si elle est réservée aux
   administrateurs.
3. Pour un `POST`, placer `Csrf::field()` dans le formulaire et terminer la
   méthode par une redirection.

## Points d'attention

- Une route POST sans `Csrf::field()` dans son formulaire répond 403.
- `Router::AUTH` sur le `GET` d'un chemin ne couvre pas son `POST`.
- Les chemins sont comparés à l'identique après normalisation : une faute de
  frappe se révèle à la requête, par un 404.
- Une action qui modifie l'état ne se déclare pas en `GET` : ces routes ne sont
  pas vérifiées par le filtre CSRF.
