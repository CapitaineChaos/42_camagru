# MVC

Modèle-Vue-Contrôleur (MVC) répartit le code d'une application web en trois
rôles : l'accès aux données, la production de l'affichage, et le traitement de
la requête qui relie les deux.

## 1 : Découpage

| Rôle | Dossier | Responsabilité | Exclu |
|------|---------|----------------|-------|
| Modèle | `app/Models/` | Lire et écrire en base. | Affichage, lecture de `$_GET` et `$_POST`. |
| Vue | `app/Views/` | Produire le HTML à partir des données reçues. | Requêtes SQL, règles métier, lecture de la requête. |
| Contrôleur | `app/Controllers/` | Lire la requête, appeler les modèles, choisir la réponse. | SQL, génération de HTML. |

Le contrôleur appelle les modèles et les vues. Les modèles et les vues ne
s'appellent pas entre eux. Une vue qui exécute un `SELECT` ou un modèle qui fait
un `echo` sort du découpage.

Deux dossiers complètent les trois rôles :

| Dossier | Contenu |
|---------|---------|
| `app/Core/` | Infrastructure indépendante du métier : routeur, classes de base, connexion, session, jeton CSRF, messages flash, envoi de mails, réglages. |
| `app/Services/` | Logique métier qui n'est ni un accès à une table ni un traitement de requête : composition d'images, notifications, avatars. |

`Services/` reçoit les traitements qui dépassent la lecture d'une requête sans
accéder à la base ni produire de HTML.

## 2 : Trajet d'une requête

```
navigateur
    ▼
Apache                     DocumentRoot = public/, réécriture vers index.php
    ▼
public/index.php           autoloader, Session::start(), table des routes
    ▼
Router::dispatch()         CSRF, existence de la route, AUTH, ADMIN
    ▼
XxxController::xxx()       lecture de $_GET / $_POST / $_SESSION
    ▼
Models, Services           requêtes préparées, traitements
    ▼
réponse                    view(), redirect() ou json()
```

`public/index.php` est le point d'entrée unique (front controller). Le
`DocumentRoot` pointe sur `public/` : le reste du code est un niveau au-dessus
et aucune URL ne l'atteint.

L'autoloader enregistré par `index.php` associe l'espace de noms au chemin :
`App\Models\Xxx` est chargé depuis `app/Models/Xxx.php`, au premier usage de la
classe. Aucun `require` de classe n'est écrit ailleurs.

## 3 : Contrôleur

Un contrôleur hérite de `Core\Controller`. Chaque méthode appelée par une route
est publique, sans argument, et se termine par l'une des trois réponses :

| Méthode | Réponse |
|---------|---------|
| `view('nom', [...])` | page HTML, vue rendue dans le layout |
| `redirect('/chemin')` | en-tête `Location`, puis fin du script |
| `json([...])` | corps JSON |

`view()` procède en deux temps :

```php
protected function view(string $view, array $data = []): void
{
    $data += $this->layoutData();

    extract($data, EXTR_SKIP);

    ob_start();
    require BASE_PATH . '/app/Views/' . $view . '.php';
    $content = ob_get_clean();

    require BASE_PATH . '/app/Views/layout.php';
}
```

`layoutData()` ajoute les données affichées sur toutes les pages : le compte
connecté, son avatar, le nombre de demandes d'ami en attente. `extract()`
transforme chaque clé du tableau en variable ; `EXTR_SKIP` empêche une clé de
remplacer une variable déjà définie, comme `$view`. `ob_start()` et
`ob_get_clean()` capturent la sortie de la vue dans `$content`, que
`layout.php` place entre le menu et le pied de page.

La vue est rendue avant le layout : les variables définies dans `layout.php`
n'existent pas dans la vue.

## 4 : Modèle

Un modèle hérite de `Core\Model`, dont le constructeur récupère la connexion
PDO partagée dans `$this->db`. Une classe correspond à une table ou à une
entité.

Une méthode de modèle porte un nom métier (`create`, `findByUsername`,
`toggle`), reçoit des valeurs déjà lues et typées par le contrôleur, et renvoie
un résultat simple : tableau associatif, liste, entier, booléen, ou `null` en
l'absence de résultat. Le SQL, les contraintes et leur traitement
(`ON CONFLICT`, `RETURNING`) restent dans la méthode ; le contrôleur ne voit que
le résultat.

Toute valeur passe par une requête préparée, avec des marqueurs nommés.

## 5 : Vue

Une vue reçoit ses données sous forme de variables et produit du HTML. Ses
conditions et ses boucles portent sur ces variables. Toute valeur issue de la
base ou d'une saisie est échappée par `htmlspecialchars()`.

Un fragment réutilisé par plusieurs vues se place dans `app/Views/partials/` et
s'inclut par `require BASE_PATH . '/app/Views/partials/xxx.php'`. Il voit les
variables de la vue qui l'inclut.

## 6 : Ajouter une fonctionnalité

1. Table ou colonne dans `database/schema.sql`, si nécessaire.
2. Méthode dans le modèle concerné, ou nouveau modèle.
3. Méthode de contrôleur.
4. Vue, ou modification d'une vue existante.
5. Route dans `config/routes.php`, avec son niveau d'accès.



## Points d'attention

- Du SQL dans un contrôleur se déplace dans un modèle.
- Une méthode de contrôleur longue, qui enchaîne des traitements sans lien avec
  la requête, se découpe vers un service de `app/Services/`.
- Une vue ne lit ni `$_POST` ni `$_GET`.
- Une action qui modifie des données se fait en POST et se termine par une
  redirection.
