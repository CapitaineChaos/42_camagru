# En-têtes HTTP et CORS

## 1 : Structure d'un échange

Requête : ligne de commande, en-têtes, corps optionnel.

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
| `Referer` | Page émettrice. Absent ou falsifié selon les cas. |
| `Origin` | Origine de la page émettrice sur les requêtes cross-site. Base du CORS. |
| `User-Agent` | Navigateur déclaré. Déclaratif. |

`Referer`, `Origin` et `User-Agent` viennent du client : exploitables pour du
confort, pas comme preuve.

## 3 : En-têtes de réponse

| En-tête | Rôle |
|---------|------|
| `Content-Type` | Type et encodage du corps (`text/html; charset=UTF-8`). |
| `Content-Length` | Taille du corps. |
| `Location` | Cible d'une redirection, avec un code 301/302. |
| `Set-Cookie` | Dépose un cookie et ses attributs `HttpOnly`, `SameSite`, `Secure`. |
| `Cache-Control` | Politique et durée de cache. |

## 4 : Dans Camagru

```php
// Controller::redirect
header('Location: ' . $path);

// PhotoController : servir un montage stocké hors du DocumentRoot
header('Content-Type: image/jpeg');
header('Content-Length: ' . (string) filesize($fichier));
header('Cache-Control: public, max-age=604800, immutable');
```

`/photo?id=12` ne porte pas d'extension : c'est le `Content-Type` qui détermine
le rendu. Les fichiers sont dans `storage/`, hors `DocumentRoot`, donc
inatteignables par URL directe ; le contrôleur vérifie les droits avant
d'émettre les en-têtes. `max-age=604800, immutable` : cache navigateur d'une
semaine sans revalidation, un montage ne changeant pas après création.

Le `Set-Cookie` de session n'est pas écrit à la main, `session_start()` le génère
depuis `session_set_cookie_params()`.

## 5 : Same-origin policy

Origine = schéma + domaine + port. `http://localhost:8080` et
`http://localhost:3000` sont deux origines.

Le navigateur interdit à du JavaScript de lire la réponse d'une autre origine.
La restriction porte sur la lecture, pas sur l'émission :

- Émission non bloquée : `<img src>`, `<form action>`, `<script src>` vers un
  autre domaine partent avec les cookies. D'où le CSRF.
- Lecture bloquée : la requête part, le serveur répond, le code appelant reçoit
  une erreur au lieu du corps.

La same-origin policy protège les données d'un site contre le JavaScript d'un
autre site, pas le serveur contre les requêtes.

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
- Un cookie ne part cross-origin qu'en `SameSite=None; Secure`, donc en HTTPS.
  Le `SameSite=Lax` de Camagru l'interdit.

CORS est un assouplissement, pas une protection. Il s'applique dans le
navigateur : `curl` et tout client non navigateur ignorent ces en-têtes.

## 7 : Cas de Camagru

Même origine pour les pages, le CSS, les images et le seul appel JavaScript :

```js
// public/js/gallery.js
const reponse = await fetch('/gallery?page=' + (derniere + 1), {
    credentials: 'same-origin',
});
```

URL relative, donc aucun en-tête CORS en jeu. `credentials: 'same-origin'` est
le défaut de `fetch`, explicite ici pour la lisibilité.

CORS deviendrait nécessaire avec un front servi ailleurs (dev server sur un
autre port, application mobile, autre domaine consommant une API Camagru).

## 8 : Mise en place

Liste blanche, origine renvoyée telle quelle si elle en fait partie, preflight
traité avant le routage, dans `public/index.php` :

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

Aucun n'est émis actuellement.

| En-tête | Effet |
|---------|-------|
| `X-Content-Type-Options: nosniff` | Interdit la détection de type hors `Content-Type` annoncé. Un upload interprété comme du HTML devient un XSS. |
| `X-Frame-Options: DENY` | Bloque l'inclusion en `<iframe>` (clickjacking). |
| `Referrer-Policy: same-origin` | Cesse d'envoyer l'URL courante aux tiers. |
| `Content-Security-Policy` | Restreint les sources de scripts ; un `<script>` injecté ne s'exécute pas. Complément de l'échappement. |

## Points d'attention

- `header()` avant tout affichage.
- `Referer`, `Origin`, `User-Agent` : jamais comme preuve d'identité.
- CORS ne remplace ni le jeton CSRF ni le contrôle d'accès.
- `Allow-Origin: *` sur une route authentifiée : inopérant avec cookies, fuite
  sans.
