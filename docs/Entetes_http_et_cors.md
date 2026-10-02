# En-têtes HTTP et CORS

## 1 : Structure d'un échange

Requête : ligne de requête, en-têtes, corps optionnel.

```http
GET /gallery?page=2 HTTP/1.1
Host: localhost:8080
Cookie: camagru_session=8f3a...
Accept: text/html,application/xhtml+xml
Referer: http://localhost:8080/gallery
```

Réponse : ligne de statut, en-têtes, corps.

```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
Content-Length: 18422
Set-Cookie: camagru_session=8f3a...; Path=/; HttpOnly; SameSite=Lax

<!DOCTYPE html>...
```

Le corps porte le contenu, les en-têtes disent comment le traiter : type, taille,
cache, session. En PHP ils s'écrivent avec `header()` et doivent partir avant le
premier octet de corps, sinon `headers already sent`.

## 2 : En-têtes de requête

Lus dans `$_SERVER`, préfixe `HTTP_`, tirets en underscores :
`Accept-Language` → `$_SERVER['HTTP_ACCEPT_LANGUAGE']`.

| En-tête | Rôle |
|---------|------|
| `Host` | Domaine visé ; permet plusieurs sites sur une IP. |
| `Cookie` | Cookies du domaine, dont l'identifiant de session. |
| `Accept` | Types de contenu acceptés par le client. |
| `Content-Type` | Format du corps envoyé (formulaire, JSON, upload). |
| `Content-Length` | Taille du corps envoyé. |
| `Referer` | Page émettrice. Peut être absent ou falsifié. |
| `Origin` | Origine de la page émettrice sur les requêtes cross-site. Base du CORS. |
| `User-Agent` | Navigateur, tel que le client le déclare. |

`Referer`, `Origin` et `User-Agent` viennent du client, qui peut les modifier :
ils servent à l'ergonomie et ne prouvent rien.

## 3 : En-têtes de réponse

| En-tête | Rôle |
|---------|------|
| `Content-Type` | Type et encodage du corps (`text/html; charset=UTF-8`). |
| `Content-Length` | Taille du corps. |
| `Location` | Cible d'une redirection, avec un code 301/302. |
| `Set-Cookie` | Dépose un cookie et ses attributs `HttpOnly`, `SameSite`, `Secure`. |
| `Cache-Control` | Politique et durée de cache. |

## 4 : Émettre un en-tête en PHP

`header()` ajoute un en-tête à la réponse ; `http_response_code()` fixe le code
de statut. Les deux s'appellent avant tout envoi de corps : un `echo`, du HTML
hors des balises PHP ou une espace avant `<?php` déclenchent l'envoi des
en-têtes, et un appel ultérieur échoue avec l'avertissement
`headers already sent`.

```php
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
```

Un second `header()` portant le même nom remplace le premier. Le second argument
à `false` ajoute une ligne supplémentaire, ce qui sert aux en-têtes
répétables comme `Set-Cookie`.

`header('Location: /chemin')` fixe aussi le statut à 302 si aucun code 3xx n'a
été posé. Le script continue après l'appel : `Controller::redirect()` le
termine par `exit`.

Un contrôleur qui sert un fichier stocké hors du `DocumentRoot` émet lui-même
les en-têtes qu'Apache poserait pour un fichier statique, après avoir vérifié
les droits du demandeur :

```php
header('Content-Type: image/jpeg');
header('Content-Length: ' . (string) filesize($fichier));
header('Cache-Control: public, max-age=604800, immutable');
readfile($fichier);
```

L'URL d'une route ne porte pas d'extension : le navigateur choisit le rendu
d'après `Content-Type`. `max-age=604800` autorise le cache navigateur pendant une
semaine ; `immutable` supprime la revalidation pendant cette durée. Ces deux
valeurs conviennent à un contenu qui ne change jamais à URL constante.

`session_start()` génère le `Set-Cookie` de la session à partir de
`session_set_cookie_params()`.

## 5 : Same-origin policy

Origine = schéma + domaine + port. `http://localhost:8080` et
`http://localhost:3000` sont deux origines.

Le navigateur interdit à du JavaScript de lire la réponse d'une autre origine.
Le navigateur laisse partir la requête et bloque la lecture de sa réponse :

- Émission non bloquée : `<img src>`, `<form action>`, `<script src>` vers un
  autre domaine partent avec les cookies, ce qui rend le CSRF possible.
- Lecture bloquée : la requête part, le serveur répond, le code appelant reçoit
  une erreur.

La same-origin policy protège les données d'un site contre leur lecture par le
JavaScript d'un autre site. Les requêtes reçues par le serveur se filtrent par
ses propres contrôles : jeton CSRF, session.

## 6 : CORS

Jeu d'en-têtes de réponse par lesquels un serveur lève cette restriction pour
des origines choisies.

| En-tête | Rôle |
|---------|------|
| `Access-Control-Allow-Origin` | Origine autorisée à lire la réponse, ou `*`. |
| `Access-Control-Allow-Credentials` | `true` : cookies envoyés et réponse livrée. |
| `Access-Control-Allow-Methods` | Méthodes autorisées. |
| `Access-Control-Allow-Headers` | En-têtes personnalisés autorisés. |
| `Access-Control-Max-Age` | Durée de cache de l'autorisation. |

Requête simple (`GET`, `HEAD`, `POST` avec un `Content-Type` de formulaire) :
elle part directement, le navigateur lit `Access-Control-Allow-Origin` dans la
réponse et livre ou jette le corps. Le serveur a traité dans les deux cas.

Requête préliminaire (preflight) : méthode non simple, corps JSON ou en-tête
maison déclenchent un `OPTIONS` préalable ; la vraie requête ne part que si la
réponse l'autorise.

Contraintes :

- `Allow-Origin: *` et `Allow-Credentials: true` sont incompatibles, le
  navigateur refuse la combinaison. Avec cookies, renvoyer l'origine exacte.
- Renvoyer l'`Origin` reçue sans filtrage autorise tout le monde. Liste blanche,
  plus `Vary: Origin` pour les caches.
- Une requête cross-origin ne porte les cookies que si `fetch()` reçoit
  `credentials: 'include'`. `SameSite` compare les sites, c'est-à-dire le schéma et le domaine enregistrable,
port exclu : le cookie de session, en `SameSite=Lax`,
  accompagne une requête venue d'un autre port de `localhost`, et jamais une
  requête `fetch()` venue d'un autre site. Un cookie destiné à un autre site doit
  être en `SameSite=None; Secure`, donc servi en HTTPS.

CORS assouplit la same-origin policy pour les origines choisies. Il s'applique dans le
navigateur : `curl` et tout client non navigateur ignorent ces en-têtes.

## 7 : Nécessité du CORS

Une requête `fetch()` vers une URL relative vise l'origine de la page : aucun
en-tête CORS n'intervient. Les requêtes du projet sont toutes dans ce cas, et la
politique de sécurité de contenu (`default-src 'self'`) interdit en outre au
JavaScript des pages de joindre une autre origine.

CORS devient nécessaire quand une page servie depuis une autre origine lit les
réponses de l'application : serveur de développement sur un autre port,
interface sur un autre domaine.

## 8 : Mise en place

Les en-têtes CORS s'émettent dans `public/index.php`, avant le routage. L'origine
reçue est comparée à une liste blanche et renvoyée telle quelle si elle en fait
partie ; une requête `OPTIONS` reçoit sa réponse sans atteindre le routeur, qui
ne connaît que `GET` et `POST` :

```php
$autorisees = ['https://front.camagru.local'];
$origine = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origine, $autorisees, true)) {
    header('Access-Control-Allow-Origin: ' . $origine);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Max-Age: 600');
    http_response_code(204);
    exit;
}
```

## 9 : En-têtes de sécurité

Émis par le vhost (`docker/web/000-default.conf`), en `Header always set` :
la directive couvre alors les réponses d'erreur, que `Header set` laisse nues.
Posés dans Apache, ils s'appliquent aussi sur les fichiers statiques,
servis sans passer par `index.php`.

| En-tête | Effet |
|---------|-------|
| `X-Content-Type-Options: nosniff` | Interdit la détection de type hors `Content-Type` annoncé. Sans lui, un fichier envoyé puis interprété comme du HTML devient un XSS. |
| `Referrer-Policy: same-origin` | N'envoie l'URL courante qu'aux requêtes de même origine. |
| `Content-Security-Policy` | Restreint les sources de scripts ; un `<script>` injecté ne s'exécute pas. Complément de l'échappement. |

`frame-ancestors 'none'` dans la CSP tient lieu de `X-Frame-Options: DENY`,
qu'elle remplace sur les navigateurs qui la comprennent.

Le module est à activer à la construction de l'image : `a2enmod headers`,
sans quoi Apache refuse de démarrer sur une directive `Header` inconnue.

## Points d'attention

- `header()` avant tout affichage.
- `Referer`, `Origin`, `User-Agent` : jamais comme preuve d'identité.
- CORS ne remplace ni le jeton CSRF ni le contrôle d'accès.
- `Allow-Origin: *` sur une route authentifiée : inopérant avec cookies, fuite
  sans.
