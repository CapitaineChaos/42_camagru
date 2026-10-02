# Ajouter une requête JavaScript

Une requête JavaScript est une requête HTTP émise par `fetch()` depuis une page
déjà affichée. Le script lit la réponse et modifie la page sans la recharger.
Côté serveur, rien ne la distingue d'une requête de navigation : elle passe par
`public/index.php`, puis par `Router::dispatch()` et ses contrôles, et atteint
une méthode de contrôleur déclarée comme toute autre route.

Une requête comporte quatre parties : la route et la méthode PHP qui répond, le
fichier JavaScript qui émet la requête, son chargement par le layout, et la
mise à jour de la page à partir de la réponse.

## 1 : `fetch()`

```js
const reponse = await fetch(url, options);
```

`fetch()` renvoie une promesse, résolue par un objet `Response` dès la
réception des en-têtes. Le corps se lit ensuite par une seconde promesse :

| Méthode | Résultat |
|---------|----------|
| `reponse.json()` | objet JavaScript ; rejet si le corps n'est pas du JSON valide |
| `reponse.text()` | chaîne |
| `reponse.blob()` | données binaires (image) |

Le corps ne se lit qu'une fois : un second appel échoue.

La promesse de `fetch()` n'est rejetée qu'en cas d'échec de transport : serveur
injoignable, requête bloquée par le navigateur (CSP, origine), requête
abandonnée. Une réponse 403, 404 ou 500 résout la promesse normalement.
`reponse.ok` vaut `true` pour un statut compris entre 200 et 299, et
`reponse.status` donne le code. Le script teste `ok` avant de lire le corps.

Options utiles :

| Option | Défaut | Rôle |
|--------|--------|------|
| `method` | `'GET'` | méthode HTTP |
| `body` | aucun | corps de la requête (`FormData`, `URLSearchParams`, chaîne) |
| `headers` | aucun | en-têtes ajoutés à la requête |
| `credentials` | `'same-origin'` | cookies joints aux requêtes de même origine, donc le cookie de session |
| `signal` | aucun | abandon de la requête par un `AbortController` |

Les redirections sont suivies sans intervention du script : la réponse obtenue
est celle de la dernière requête de la chaîne. `reponse.redirected` vaut alors
`true` et `reponse.url` contient l'adresse finale.

`await` n'est utilisable que dans une fonction `async` ou dans un module
(`type="module"`). La structure d'un appel est la suivante :

```js
async function charger() {
    try {
        const reponse = await fetch('/xxx?id=' + encodeURIComponent(id));
        if (!reponse.ok) {
            throw new Error(String(reponse.status));
        }
        const donnees = await reponse.json();
        // mise à jour de la page
    } catch {
        // message d'échec ou retour à l'état initial
    }
}
```

Le bloc `catch` reçoit l'échec de transport, le statut hors 2xx levé par le
`throw` et l'erreur de `json()` sur un corps qui n'est pas du JSON.

## 2 : Forme de la réponse

`Core\Controller` fournit deux réponses lisibles par un script.

| Réponse | Côté PHP | Côté JavaScript |
|---------|----------|-----------------|
| JSON | `$this->json([...])` | `await reponse.json()` |
| page HTML | `$this->view('xxx', [...])` | `await reponse.text()`, puis `DOMParser` |

Le JSON transporte des valeurs que le script place dans des éléments existants
de la page : un état, un compteur, un message. La route est écrite pour le
script.

La page HTML sert à récupérer des éléments déjà rendus par une vue. Le script
interroge une route `GET` existante, analyse le texte reçu et en extrait des
nœuds :

```js
const doc = new DOMParser().parseFromString(await reponse.text(), 'text/html');
for (const noeud of doc.querySelectorAll('#xxx > .yyy')) {
    cible.append(document.adoptNode(noeud));
}
```

Aucun code serveur n'est ajouté, et le balisage reste défini à un seul endroit,
la vue. En contrepartie, le serveur génère et transmet la page complète, layout
compris, pour n'en garder qu'une partie. `view()` inclut toujours le layout :
aucune méthode de `Controller` ne rend une vue seule.

## 3 : Méthode de contrôleur

La route se déclare dans `config/routes.php` comme toute autre route, avec son
niveau d'accès. La méthode lit ses entrées dans
`$_GET` ou `$_POST` et les valide comme celles d'un formulaire : une route
appelée par un script peut aussi l'être par `curl` ou par n'importe quel client.

```php
public function xxx(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $ligne = (new Xxx())->find($id);

    if ($ligne === null) {
        http_response_code(404);
        $this->json(['error' => 'Not found.']);
        return;
    }

    $this->json([
        'id'    => $id,
        'value' => $ligne['value'],
    ]);
}
```

`json()` envoie l'en-tête `Content-Type: application/json; charset=utf-8` puis
le tableau encodé. Trois propriétés en découlent :

- Le statut est 200 par défaut. Un autre code se fixe par
  `http_response_code()` avant l'appel.
- `redirect()` termine le script par `exit` ; `json()` rend la main à la
  méthode, qui retourne juste après. Sinon, la suite de son code s'exécute et
  peut ajouter du contenu au corps.
- L'encodage utilise `JSON_THROW_ON_ERROR` : une valeur non encodable, comme une
  chaîne en UTF-8 invalide, lève une exception et produit une erreur 500.

Une route publique répond à tout client. Sa réponse ne contient que des données
que la page affiche déjà à ce visiteur : jamais d'adresse électronique, de hash
ou de jeton.

## 4 : Requête GET

Les paramètres passent dans la chaîne de requête. Chaque valeur est encodée,
par `encodeURIComponent()` ou par `URLSearchParams` :

```js
fetch('/xxx?q=' + encodeURIComponent(saisie));
fetch('/xxx?' + new URLSearchParams({ q: saisie, page: 2 }));
```

Sans encodage, un `&`, un `#`, un `+` ou une espace contenus dans une valeur
coupent ou modifient les paramètres reçus dans `$_GET`.

Le routeur ne vérifie aucun jeton sur une requête `GET`. Une route `GET` ne
modifie donc aucune donnée.

## 5 : Requête POST

### Corps

Le routeur compare `$_POST['csrf_token']` au jeton de session avant toute autre
opération. PHP ne remplit `$_POST` que pour deux formats de corps :
`application/x-www-form-urlencoded` et `multipart/form-data`. Le corps est donc
un objet `FormData` ou `URLSearchParams`. Un corps JSON laisse `$_POST` vide et
la requête reçoit un 403.

Avec `FormData`, le navigateur fixe l'en-tête `Content-Type` à `multipart/form-data; boundary=...`, et PHP a besoin de la
valeur `boundary` pour découper le corps. Une valeur écrite à la main ne la
contient pas : `$_POST` reste vide et la requête reçoit un 403.

### Jeton CSRF

Le jeton doit figurer dans la page pour que le script puisse le joindre.

Si la requête remplace la soumission d'un formulaire, `new FormData(form)`
reprend tous les champs nommés du formulaire, dont le champ caché émis par
`Csrf::field()` :

```js
form.addEventListener('submit', async (evenement) => {
    evenement.preventDefault();
    const reponse = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
    });
    // ...
});
```

Sans formulaire, la vue émet le jeton dans un attribut `data-` d'un élément que
le script lit :

```php
<section id="xxx" data-csrf="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
```

```js
const donnees = new FormData();
donnees.append('csrf_token', section.dataset.csrf);
donnees.append('id', id);
await fetch('/xxx', { method: 'POST', body: donnees });
```

Le jeton est le même pour toute la session. Il reste valide tant que la session
existe, quel que soit le nombre de requêtes envoyées.

### Fichier

Un fichier ou un `Blob` s'ajoute au `FormData` avec un nom de fichier, et arrive
côté PHP dans `$_FILES` :

```js
donnees.append('file', blob, 'capture.png');
```

Les limites de `docker/web/uploads.ini` s'appliquent. Un fichier qui dépasse
`upload_max_filesize` arrive avec `$_FILES['file']['error']` à
`UPLOAD_ERR_INI_SIZE`. Un corps qui dépasse `post_max_size` arrive avec un
`$_POST` vide, donc sans jeton : la réponse est un 403.

### Réponse d'une action

Les actions POST du projet se terminent par `redirect()`. Appelée par `fetch()`,
une telle action renvoie un 302 que le navigateur suit : le script reçoit la
page HTML de la cible, avec un statut 200, sans moyen de connaître le résultat
de l'action. Le `GET` suivi consomme aussi les messages déposés par `Flash` :
ils figurent dans la page reçue par le script et ne sont jamais affichés.

Une action dont le script doit connaître le résultat répond par `json()`. Deux
organisations sont possibles.

Une route dédiée au script, distincte de celle du formulaire, répond toujours
en JSON. Les deux méthodes partagent la validation et l'appel au modèle.

Une même méthode répond selon le client. Le script annonce le format attendu
par l'en-tête `Accept`, que PHP expose dans `$_SERVER['HTTP_ACCEPT']` :

```js
fetch('/xxx', {
    method: 'POST',
    body: donnees,
    headers: { 'Accept': 'application/json' },
});
```

```php
if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    $this->json(['ok' => true]);
    return;
}
Flash::notice('Done.');
$this->redirect('/xxx');
```

Un formulaire envoyé sans script ne porte pas cette valeur dans `Accept` et
reçoit la redirection.

## 6 : Réponses du routeur

Le routeur répond à ses refus par des pages HTML ou des redirections, émises
avant l'appel du contrôleur.

| Situation | Réponse reçue par le script |
|-----------|-----------------------------|
| POST sans jeton valide | 403, page d'erreur HTML |
| route inexistante pour cette méthode | 404, page d'erreur HTML |
| `Router::AUTH` ou `Router::ADMIN` sans session | redirection suivie : 200, page de connexion, `reponse.redirected` à `true` |
| `Router::ADMIN` sans rang d'admin | 403, page d'erreur HTML |
| exception non interceptée en PHP | 500 |

Le cas de la session absente produit un statut 200 : le test de `ok` ne suffit
pas. Il se présente dès qu'une session expire pendant que la page reste
ouverte, après `session.lifetime` secondes d'inactivité. Sur une route
protégée, le script teste aussi `reponse.redirected` ; sur une route JSON,
`reponse.json()` échoue sur la page HTML reçue et l'erreur aboutit dans le
`catch`.

## 7 : Fichier JavaScript et chargement

Le script se place dans `public/js/`. Apache sert ce répertoire directement,
sans passer par PHP. Le layout charge chaque script en fin de `<body>`,
uniquement sur la vue qui l'utilise :

```php
<?php if (($view ?? '') === 'xxx'): ?>
<script src="/js/xxx.js?v=<?= $v ?>" defer></script>
<?php endif; ?>
```

`defer` diffère l'exécution jusqu'à la fin de l'analyse du document : les
éléments de la vue existent quand le script démarre. `type="module"` a le même
effet et autorise en plus `import` et `await` hors fonction.

Après toute modification d'un fichier JavaScript, incrémenter `assets.version`
dans `config/settings.php` : la valeur change le `?v=` de l'URL et force le
navigateur à recharger le fichier.

La politique de sécurité de contenu émise par Apache
(`docker/web/000-default.conf`) impose deux contraintes :

- `script-src 'self'` : aucun `<script>` en ligne ni attribut `onclick`. Le code
  est dans un fichier et les événements s'attachent par `addEventListener`.
- `default-src 'self'` couvre `connect-src` : `fetch()` n'atteint que l'origine
  de la page. Une requête vers une autre origine est bloquée par le navigateur,
  et la promesse est rejetée.

Le script commence par chercher les éléments dont il a besoin et s'arrête si
l'un d'eux manque. Une vue peut ne pas les rendre dans certains états, par
exemple pour un visiteur non connecté.

Les valeurs dont le script a besoin (identifiant, numéro de page, nombre total
de pages) sont émises par la vue dans des attributs `data-`, échappés par
`htmlspecialchars()`, et lues par `element.dataset` :

```php
<ul id="xxx" data-page="<?= (int) $page ?>" data-pages="<?= (int) $pages ?>">
```

```js
const page = Number(liste.dataset.page);
```

`dataset` convertit les noms : `data-last-id` se lit `dataset.lastId`. Les
valeurs sont toujours des chaînes.

Les requêtes JavaScript du projet complètent une page qui fonctionne sans
script : les liens et formulaires restent dans le balisage, et le script les
masque ou intercepte leur événement (`preventDefault()`). Une nouvelle requête
suit la même règle : la route et le formulaire existent d'abord, le script
s'ajoute ensuite.

## 8 : Mise à jour de la page

Une valeur issue d'une réponse JSON s'insère par `textContent`. `json_encode()`
n'échappe pas le HTML : une chaîne saisie par un utilisateur et insérée par
`innerHTML` est interprétée comme du balisage.

Les nœuds extraits d'une page par `DOMParser` proviennent d'une vue qui a déjà
échappé ses valeurs. Ils s'insèrent par `append()`. Les scripts contenus dans
un document produit par `DOMParser` ne sont pas exécutés.

Un message d'état (chargement, échec) se place dans un élément portant
`aria-live="polite"` : les lecteurs d'écran annoncent ses changements de
contenu.

Pendant la requête, le bouton qui l'a déclenchée est désactivé
(`bouton.disabled = true`) et réactivé dans un bloc `finally`. Sans cela, des
clics répétés envoient autant de requêtes.

## 9 : Requêtes concurrentes

Deux requêtes envoyées l'une après l'autre peuvent recevoir leurs réponses dans
l'ordre inverse. Trois mécanismes limitent ce cas :

| Mécanisme | Mise en œuvre |
|-----------|---------------|
| drapeau | une variable `enCours`, testée au début de la fonction et remise à `false` dans `finally` |
| délai de saisie | `clearTimeout()` puis `setTimeout()` à chaque `input` : la requête part après une pause de frappe |
| contrôle de la réponse | la réponse renvoie le paramètre reçu ; le script l'ignore s'il ne correspond plus à l'état de la page |

`AbortController` annule la requête précédente avant d'en émettre une nouvelle :

```js
let controleur = null;

async function charger(saisie) {
    controleur?.abort();
    controleur = new AbortController();
    try {
        const reponse = await fetch('/xxx?q=' + encodeURIComponent(saisie), {
            signal: controleur.signal,
        });
        // ...
    } catch (erreur) {
        if (erreur.name === 'AbortError') {
            return;
        }
        // échec réel
    }
}
```

Côté serveur, PHP enregistre les sessions dans des fichiers et verrouille le
fichier de la session pendant toute la requête. Les requêtes simultanées d'un
même navigateur sont donc traitées l'une après l'autre.

`Session::start()` régénère l'identifiant de session toutes les
`session.regenerate` secondes et supprime l'ancien. Une requête partie avec
l'ancien cookie au moment de la rotation peut arriver sur une session vide, et
le routeur la traite alors comme une requête sans session.

## 10 : Vérification

L'onglet Réseau des outils de développement affiche, pour chaque requête, la
méthode, le statut, les en-têtes envoyés et reçus, le corps envoyé et la
réponse. Les violations de la politique de sécurité de contenu et les erreurs
du script apparaissent dans la console.

Une route `GET` publique s'interroge aussi par `curl` :

```sh
curl -i 'http://localhost:8080/xxx?id=1'
```

Une route protégée ou une requête POST demande le cookie de session et le
jeton. Le plus direct est d'appeler `fetch()` depuis la console du navigateur,
sur une page du site ouverte avec une session : le cookie est joint, et le jeton
se lit dans le champ caché d'un formulaire de la page.

## Récapitulatif

| Étape | Fichier |
|-------|---------|
| Route, niveau d'accès | `config/routes.php` |
| Méthode : lecture, validation, `json()` ou `view()` | `app/Controllers/XxxController.php` |
| Valeurs pour le script (`data-`, `Csrf::token()`), éléments à remplacer | `app/Views/xxx.php` |
| Émission de la requête, mise à jour de la page | `public/js/xxx.js` |
| Chargement du script sur la vue | `app/Views/layout.php` |
| Rechargement navigateur | `config/settings.php` (`assets.version`) |
| Limites des envois de fichiers | `docker/web/uploads.ini` |
