# Session utilisateur

## 1 : Principe

Une session PHP associe un identifiant, gardé côté navigateur dans un cookie, à
des données stockées côté serveur. L'état de connexion est dans :

```php
$_SESSION['user']
```

Le cookie ne transporte que l'identifiant ; les données restent sur le serveur.

## 2 : Le cookie de session

Configuré dans `Core/Session.php` (`session_set_cookie_params`) :

| Paramètre | Valeur | Rôle |
|-----------|--------|------|
| nom | `camagru_session` (`session.name`) | Remplace le `PHPSESSID` par défaut. |
| `httponly` | `true` | Cookie inaccessible au JavaScript (`document.cookie`), ce qui limite le vol de session via XSS. |
| `samesite` | `Lax` | Cookie non envoyé sur les requêtes POST cross-site. |
| `secure` | auto | `true` si la requête est en HTTPS (`$_SERVER['HTTPS']` ou port 443), `false` en HTTP local. |
| `lifetime` | `0` (`session.cookie_lifetime`) | Cookie de session, effacé à la fermeture du navigateur. |
| `path` | `/` | Valable sur tout le site. |

## 3 : Démarrage

`Session::start()` est appelé au point d'entrée (`public/index.php`) avant le
routage. Il pose les paramètres du cookie, ouvre la session, puis :

- expiration d'inactivité : au-delà de `session.lifetime` (7200 s) sans requête,
  la session est détruite (`last_activity`).
- rotation d'identifiant : l'ID est régénéré tous les `session.regenerate`
  (900 s), sans reconnexion.

## 4 : Connexion

Après validation des identifiants (`AuthController`) :

```php
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'       => $userId,
    'username' => $user['username'],
    'is_admin' => (new User())->isAdmin($userId),
];
```

`session_regenerate_id(true)` remplace l'ID à la connexion et supprime l'ancien.

## 5 : Lecture

L'utilisateur est connecté si :

```php
!empty($_SESSION['user'])
```

Test utilisé pour l'affichage conditionnel et pour les routes réservées.

## 6 : Déconnexion

```php
$_SESSION = [];
session_destroy();
```

## 7 : Vol de session

Détenir l'identifiant suffit à usurper la session. Voies d'obtention et réglage
qui les ferme :

| Voie | Mécanisme | Contre-mesure |
|------|-----------|---------------|
| XSS | `document.cookie` lu par un script injecté | `httponly` |
| Écoute réseau | cookie en clair sur HTTP | `secure` + HTTPS |
| CSRF | cookie réutilisé à l'insu de la victime, sans lecture de l'ID | jeton CSRF + `SameSite=Lax` |
| Fixation | ID connu imposé avant connexion | `session_regenerate_id(true)` à la connexion |
| Poste partagé | session laissée ouverte | expiration 7200 s, rotation 900 s, cookie non persistant |
| Force brute | énumération d'identifiants valides | entropie de l'ID, voir ci-dessous |

L'identifiant est produit par le générateur aléatoire cryptographique de PHP.
`session.sid_length` vaut 32 caractères et `session.sid_bits_per_character` 4,
soit 128 bits d'entropie.
