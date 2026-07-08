# Protection des routes

Créer un jeu d'enregistrements comme get / set et rediriger vers la page de login si l'utilisateur n'est pas connecté.


Router.php :

```php
final class Router
{
    private array $protectedRoutes = [];


    public function requireAuth(string $method, string $path): void
    {
        $this->protectedRoutes[$method][$this->normalize($path)] = true;
    }

    public function dispatch(string $httpMethod, string $path): void
    {
        $normalizedPath = $this->normalize($path);

        if (!empty($this->protectedRoutes[$httpMethod][$normalizedPath]) && empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        $action = $this->routes[$httpMethod][$normalizedPath] ?? null;

        ...
    }
}
```

routes.php : Déclarer les routes protégées par l'authentification

```php
$router->requireAuth('GET', '/preferences');
$router->requireAuth('POST', '/preferences');
$router->requireAuth('GET', '/profile');
```
