# Transmettre des données entre requêtes

Chaque requête exécute PHP à partir d'un état vide. Une
valeur connue pendant une requête n'existe plus à la suivante, sauf si elle est
transmise. Cinq moyens existent :

| Moyen | Porteur | Lu en PHP par | Modifiable par le client |
|-------|---------|---------------|--------------------------|
| chaîne de requête | URL, après `?` | `$_GET` | oui |
| corps d'un POST | formulaire ou `fetch()` | `$_POST`, `$_FILES` | oui |
| session | serveur, désignée par un cookie | `$_SESSION` | non |
| cookie | navigateur | `$_COOKIE` | oui |
| attribut `data-` | page HTML, vers JavaScript | sans objet | oui |

Toute valeur venue de l'URL, d'un corps, d'un cookie ou de la page est
contrôlée côté serveur à chaque requête. Seule la session contient des données
que le client ne peut pas modifier.

## 1 : Chaîne de requête

### Syntaxe

```
/xxx?page=2&q=chat%20noir#resultat
```

| Partie | Rôle |
|--------|------|
| `/xxx` | chemin, qui désigne la route |
| `?` | début de la chaîne de requête |
| `page=2`, `q=chat%20noir` | paramètres `nom=valeur`, séparés par `&` |
| `#resultat` | fragment |

Le fragment n'est jamais envoyé au serveur. Le navigateur s'en sert pour faire
défiler la page jusqu'à l'élément dont l'`id` vaut `resultat`.

### Encodage

Un caractère réservé (`&`, `=`, `?`, `#`, `+`, espace) ou non ASCII contenu dans
une valeur s'encode en `%` suivi de son code hexadécimal : l'espace devient
`%20` (ou `+`), `&` devient `%26`, `é` devient `%C3%A9`. Sans encodage, un `&`
dans une valeur commence un nouveau paramètre.

En PHP, une URL se construit avec `http_build_query()`, qui encode chaque nom et
chaque valeur :

```php
$url = '/xxx?' . http_build_query(['q' => $saisie, 'page' => 2]);
```

`rawurlencode()` encode une valeur isolée. En JavaScript, les équivalents sont
`URLSearchParams` et `encodeURIComponent()`.

Placée dans un attribut `href`, l'URL passe en plus par `htmlspecialchars()`,
comme toute valeur insérée dans le HTML ; le navigateur reconvertit `&amp;` en
`&` avant de suivre le lien.

### Réception

PHP décode la chaîne de requête et remplit `$_GET` avant l'exécution du script.

| Chaîne reçue | `$_GET` |
|--------------|---------|
| `?page=2` | `['page' => '2']` |
| `?page=` | `['page' => '']` |
| `?page` | `['page' => '']` |
| `?a=1&a=2` | `['a' => '2']` : la dernière valeur l'emporte |
| `?a[]=1&a[]=2` | `['a' => ['1', '2']]` |
| `?a[x]=1` | `['a' => ['x' => '1']]` |
| `?a.b=1` | `['a_b' => '1']` : points et espaces des noms deviennent `_` |

Les valeurs sont toujours des chaînes, ou des tableaux de chaînes. Une lecture
complète prévoit la clé absente, la conversion et les bornes :

```php
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
$q    = trim((string) ($_GET['q'] ?? ''));
```

Le transtypage `(string)` ramène à une chaîne un paramètre envoyé sous forme de
tableau ; sans lui, `trim()` sur un tableau lève une `TypeError`.

### Passage par Apache et le routeur

La règle de réécriture du vhost envoie toute URL sans fichier correspondant vers
`index.php`. La cible `index.php` ne contient pas de `?` : Apache conserve alors
la chaîne de requête d'origine. Le drapeau `QSA` n'agit que lorsque la cible
porte ses propres paramètres, qu'il fusionne avec ceux de la requête.

`index.php` ne transmet au routeur que le chemin
(`parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`) : la chaîne de requête ne
participe pas au choix de la route, et `/xxx?id=1` comme `/xxx?id=2` atteignent
la même méthode.

### Usage

La chaîne de requête porte ce qui désigne ce qu'on lit : identifiant d'un
élément, numéro de page, recherche, filtre. L'URL obtenue se met en favori, se
partage et se recharge, et chaque rechargement redonne la même page.

Une requête `GET` ne modifie aucune donnée : le routeur ne vérifie aucun jeton
CSRF sur `GET`, et un navigateur peut précharger ou rejouer une URL.

L'URL complète apparaît dans l'historique du navigateur et dans les logs
d'Apache. Un secret n'y figure que s'il est à usage unique et limité dans le
temps, comme le jeton d'un lien de confirmation reçu par mail. L'en-tête
`Referrer-Policy: same-origin` du vhost empêche l'envoi de l'URL courante aux
autres sites.

Apache refuse une ligne de requête de plus de 8190 octets (réponse 414). Une
donnée volumineuse passe dans un corps POST.

## 2 : Corps d'un POST

Un formulaire en `method="post"` envoie ses champs dans le corps de la requête.
PHP les place dans `$_POST`, et les fichiers dans `$_FILES`. Les règles de
réception sont celles de `$_GET` : clés pouvant manquer, valeurs en chaînes,
`nom[]` pour un tableau.

Champs non transmis par le navigateur :

| Champ | Envoyé |
|-------|--------|
| case à cocher décochée | non : la clé est absente de `$_POST` |
| champ `disabled` | non |
| champ sans attribut `name` | non |
| bouton `submit` | seulement celui qui a déclenché l'envoi, s'il porte un `name` |

Une case à cocher se lit donc par sa présence : `isset($_POST['actif'])`.

Un champ caché (`<input type="hidden">`) transmet une valeur que la page
connaît déjà, par exemple l'identifiant de l'élément visé ou la page à laquelle
revenir. Le client peut la modifier comme tout autre champ : le contrôleur
vérifie que l'utilisateur a le droit d'agir sur l'élément désigné, et ne prend
jamais dans le corps une donnée que la session fournit (identifiant de
l'utilisateur, rang d'admin).

Le POST sert aux actions qui modifient des données. Le routeur vérifie le jeton
CSRF de chaque POST, et la méthode se termine par une redirection.

## 3 : Redirection

Une redirection transmet des données par l'URL de sa cible :

```php
$this->redirect('/xxx?page=' . $page . '#element-' . $id);
```

Le navigateur émet un `GET` vers cette URL. La page reçoit `page` dans `$_GET`
et le fragment la fait défiler jusqu'à l'élément traité. Les valeurs insérées
sont des entiers déjà validés, ou passent par `http_build_query()`.

Un message à afficher après la redirection passe par la session, avec `Flash`
(section 4). Placé dans l'URL, il s'enregistrerait dans l'historique et un
visiteur pourrait le forger.

## 4 : Session

`$_SESSION` est un tableau conservé côté serveur entre les requêtes d'un même
navigateur, désigné par le cookie `camagru_session`. Une valeur écrite pendant une requête est lisible à
la suivante :

```php
$_SESSION['xxx'] = $valeur;          // requête 1
$valeur = $_SESSION['xxx'] ?? null;  // requête 2
```

La session porte l'identité de l'utilisateur connecté (`$_SESSION['user']`), le
jeton CSRF et les messages `Flash`. `Flash::notice()` et `Flash::errors()`
écrivent dans `$_SESSION['flash']`, et `Flash::pull()` lit puis efface : le
message survit à exactement une redirection.

La session est partagée par tous les onglets du navigateur. Une valeur liée à
une page précise (élément en cours, étape d'un formulaire) y est écrasée dès
qu'un autre onglet écrit la même clé ; elle passe par l'URL ou par un
champ caché.

La session disparaît à la déconnexion, après `session.lifetime` secondes
d'inactivité, ou à la fermeture du navigateur. Une donnée durable va en base.

## 5 : Cookie

`setcookie()` demande au navigateur de conserver une valeur, renvoyée ensuite
avec chaque requête et lue dans `$_COOKIE`. Le client peut la lire et la
modifier, sauf l'attribut `HttpOnly` qui en interdit la lecture par JavaScript.
Le projet n'utilise que le cookie de session. Une préférence d'affichage propre
à un navigateur se garde dans `localStorage`, côté JavaScript.

## 6 : De PHP vers JavaScript

Le script d'une page lit les valeurs que la vue a écrites dans des attributs
`data-` :

```php
<ul id="xxx" data-page="<?= (int) $page ?>">
```

```js
const page = Number(document.getElementById('xxx').dataset.page);
```

Dans l'autre sens, le script transmet des valeurs au serveur par une requête
`fetch()`, en `GET` ou en `POST`, selon les règles des sections 1 et 2.

## 7 : Choix du moyen

| Donnée | Moyen |
|--------|-------|
| élément affiché, page, recherche, filtre | chaîne de requête |
| saisie d'un formulaire, action qui modifie des données | corps POST |
| élément visé par une action | champ caché, vérifié côté serveur |
| identité, droits, jeton CSRF | session |
| message après redirection | session, par `Flash` |
| retour à une position dans la page | fragment `#` de l'URL de redirection |
| valeur nécessaire à un script | attribut `data-` |
| donnée durable | base de données |
