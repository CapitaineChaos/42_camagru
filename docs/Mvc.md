# MVC

## 1 : Découpage

| Rôle | Dossier | Responsabilité | Exclu |
|------|---------|----------------|-------|
| Modèle | `app/Models/` | Lire et écrire en base. | Affichage, lecture de `$_POST`. |
| Vue | `app/Views/` | Produire le HTML à partir de données reçues. | Requêtes SQL, règles métier. |
| Contrôleur | `app/Controllers/` | Lire la requête, appeler les modèles, choisir la vue. | SQL, génération de HTML. |

Le contrôleur appelle les modèles et les vues. Les modèles et les vues ne
s'appellent pas entre eux. Une vue qui exécute un `SELECT` ou un modèle qui fait
un `echo` sort du découpage.

## 2 : Trajet d'une requête

```
navigateur
    │  GET /gallery?page=2
    ▼
Apache (000-default.conf)       DocumentRoot = public/, réécriture vers index.php
    ▼
public/index.php                autoloader, Session::start(), construction du routeur
    ▼
Core/Router::dispatch()         CSRF, authentification, droits admin, résolution de route
    ▼
Controllers/GalleryController   lecture de $_GET, appel des modèles, préparation des données
    ▼
Models/Image, Models/Comment    requêtes préparées PDO
    ▼
Views/gallery.php               HTML de la page, échappé
    ▼
Views/layout.php                en-tête, menu, pied de page autour du contenu
    ▼
navigateur
```

`public/index.php` est le front controller. `DocumentRoot` pointe sur `public/`,
le reste du code est un niveau au-dessus et n'est atteignable par aucune URL.

## 3 : Point d'entrée

```php
// public/index.php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Session::start();

$router = new Router();
(require BASE_PATH . '/config/routes.php')($router);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
```

L'autoloader mappe le namespace sur le chemin : `App\Models\Image` est chargé
depuis `app/Models/Image.php`. Aucun `require` n'est écrit ailleurs.

## 4 : Routeur

Table de routes déclarée dans `config/routes.php` :

```php
$router->get('/gallery', [GalleryController::class, 'gallery']);
$router->post('/gallery/like', [GalleryController::class, 'like']);
$router->requireAuth('POST', '/gallery/like');
```

Une route associe méthode HTTP + chemin à une méthode de contrôleur. `GET /login`
et `POST /login` sont deux routes : affichage du formulaire, traitement.

`Router::dispatch()` applique trois filtres avant l'appel :

| Filtre | Condition | Réponse |
|--------|-----------|---------|
| CSRF | tout `POST` sans jeton valide | 403 |
| Authentification | route en `requireAuth`, session vide | redirection `/login` |
| Droits | route en `requireAdmin`, `is_admin` absent | 403 |

Puis :

```php
[$controller, $method] = $action;
(new $controller())->{$method}();
```

Les filtres étant dans le routeur, une nouvelle route POST est couverte par la
vérification CSRF sans code supplémentaire.

## 5 : Contrôleur

```php
// Controllers/HomeController.php
final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home', ['title' => 'Home']);
    }
}
```

`Controller::view()` rend en deux temps :

```php
protected function view(string $view, array $data = []): void
{
    $data += (new LayoutDataProvider())->fromSession($_SESSION);

    extract($data, EXTR_SKIP);

    ob_start();
    require BASE_PATH . '/app/Views/' . $view . '.php';
    $content = ob_get_clean();

    require BASE_PATH . '/app/Views/layout.php';
}
```

`extract()` convertit les clés du tableau en variables : `['images' => ...]`
devient `$images` dans la vue. `ob_start()` / `ob_get_clean()` capturent le HTML
de la vue dans `$content`. `layout.php` est inclus ensuite et place `$content`
entre le menu et le pied de page ; la vue ne contient donc pas de `<html>`.

La vue est rendue avant le layout : une variable définie dans `layout.php`
(`$v`, par exemple) n'existe pas dans la vue.

## 6 : Modèle

```php
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }
}
```

Les méthodes exposées sont nommées en termes métier. `Like::toggle()` :

```php
// Models/Like.php
public function toggle(int $imageId, int $userId): bool
{
    $stmt = $this->db->prepare(
        'INSERT INTO likes (image_id, user_id) VALUES (:image_id, :user_id)
         ON CONFLICT (image_id, user_id) DO NOTHING'
    );
    $stmt->execute(['image_id' => $imageId, 'user_id' => $userId]);

    if ($stmt->rowCount() === 1) {
        return true;
    }

    $this->db->prepare('DELETE FROM likes WHERE image_id = :image_id AND user_id = :user_id')
        ->execute(['image_id' => $imageId, 'user_id' => $userId]);

    return false;
}
```

Le contrôleur appelle `toggle()` et reçoit un booléen ; la contrainte d'unicité
et le `ON CONFLICT` restent dans le modèle. Toutes les valeurs passent par des
marqueurs nommés.

## 7 : Vue

```php
<!-- Views/home.php -->
<h1 class="logo"><?= \App\Core\Svg::inline('logo') ?></h1>

<div class="polaroids">
    <figure class="polaroid polaroid-left">
        <img src="/images/perso1.png" alt="Montage with the cat ears filter">
    </figure>
</div>
```

Toute valeur issue de l'utilisateur ou de la base est échappée par
`htmlspecialchars()`. Les conditions portent sur des variables déjà
préparées par le contrôleur, pas sur des données à requêter.

## 8 : Core et Services

| Dossier | Contenu |
|---------|---------|
| `app/Core/` | Plomberie : `Router`, `Controller`, `Model`, `Database`, `Session`, `Csrf`, `Flash`, `Mailer`, `Settings`, `Secret`. Indépendant du métier Camagru. |
| `app/Services/` | Logique métier hors modèle et hors contrôleur : `Montage` (composition d'images), `Notifications` (emails), `Avatars`, `Overlays`, `LayoutDataProvider`. |

Composer une image avec un overlay n'est ni une requête SQL, ni de l'affichage,
ni du routage ; ce code est dans `Services/` plutôt que dans les contrôleurs.

## 9 : Aller-retour complet

Un « j'aime » sur un montage.

Vue — formulaire POST portant le jeton CSRF :

```php
<form method="post" action="/gallery/like">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="page" value="<?= $page ?>">
    <button type="submit"><?= (int) $image['liked'] === 1 ? 'Unlike' : 'Like' ?></button>
</form>
```

Le libellé vient de `liked`, calculé par le contrôleur.

Routeur — `POST /gallery/like` : jeton vérifié, session vérifiée
(`requireAuth`), appel de `GalleryController::like()`.

Contrôleur :

```php
public function like(): void
{
    $id = (int) ($_POST['id'] ?? 0);

    if ($this->cible($id) !== null) {
        (new Like())->toggle($id, (int) $_SESSION['user']['id']);
    }

    $this->redirect($this->retour($id));
}
```

Modèle — `Like::toggle()` ajoute ou retire la ligne.

Réponse — aucune vue rendue, un `Location:`. Le navigateur refait un `GET` ;
un rafraîchissement ne redéclenche pas le POST (Post/Redirect/Get).

## 10 : Ajouter une fonctionnalité

1. Table dans `database/schema.sql` si nécessaire.
2. Méthode dans le modèle concerné, ou nouveau modèle.
3. Méthode du contrôleur.
4. Vue ou fragment de vue.
5. Route dans `config/routes.php`, avec `requireAuth` / `requireAdmin`.


## Points d'attention

- Du SQL dans un contrôleur : à déplacer dans un modèle.
- Une méthode de contrôleur qui dépasse une trentaine de lignes : candidate à un
  service dans `app/Services/`.
- Une vue ne lit ni `$_POST` ni `$_GET`.
- Une action modifiant l'état se fait en POST et se termine par une redirection.
- `$this->view()` rend la vue avant le layout : les variables du layout ne sont
  pas disponibles dans la vue.
