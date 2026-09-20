# Session utilisateur

## 1 : Principe

Une session PHP associe un identifiant de session, gardé côté navigateur dans un
cookie, à des données stockées côté serveur. L'état de connexion est dans :

```php
$_SESSION['user']
```

Le cookie ne contient **que l'identifiant** ; les données (`user`, etc.) ne
quittent jamais le serveur.

## 2 : Le cookie de session

Configuré dans `Core/Session.php` (`session_set_cookie_params`) :

- **nom** : `camagru_session` (`session.name`).
- **`httponly` = true** : le cookie n'est pas lisible en JavaScript (`document.cookie`).
- **`samesite` = Lax** : le cookie n'est pas envoyé sur un POST cross-site.
- **`secure`** : activé automatiquement sous HTTPS (le cookie ne part qu'en HTTPS).
- **`lifetime` = 0** (`session.cookie_lifetime`) : cookie de session, effacé à la
  fermeture du navigateur.

## 3 : Démarrage

`Session::start()` est appelé au point d'entrée (`public/index.php`) avant le
routage. Il pose les paramètres du cookie, ouvre la session, puis :

- **expiration d'inactivité** : au-delà de `session.lifetime` (7200 s) sans
  requête, la session est détruite (`last_activity`).
- **rotation d'identifiant** : l'ID est régénéré tous les `session.regenerate`
  (900 s) même sans reconnexion.

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

Sert à distinguer la navigation connectée de la navigation anonyme, et à garder
les pages réservées (voir `Protection_de_routes.md`).

## 6 : Déconnexion

```php
$_SESSION = [];
session_destroy();
```

## 7 : Vol de session sans protection

Voler l'identifiant contenu dans le cookie suffit à usurper la session. Chaque
voie est fermée par un réglage précis :

- **Lecture par script (XSS)** : `document.cookie` donnerait l'ID à un script
  injecté. `httponly` l'en empêche. (voir `Xss.md`)
- **Écoute réseau** : sur HTTP le cookie circule en clair et se capte au sniff.
  `secure` + HTTPS évitent qu'il parte hors d'un canal chiffré.
- **Requête forgée (CSRF)** : l'attaquant n'a pas besoin de l'ID, il fait
  utiliser le cookie de la victime à son insu. Jeton CSRF + `SameSite=Lax`.
  (voir `Csrf.md`)
- **Fixation de session** : imposer à la victime un ID connu avant qu'elle se
  connecte. `session_regenerate_id(true)` à la connexion invalide l'ID imposé.
- **Session laissée ouverte / poste partagé** : expiration d'inactivité (7200 s),
  rotation d'ID (900 s) et cookie effacé à la fermeture du navigateur réduisent
  la fenêtre d'usurpation.

L'identifiant lui-même est généré aléatoirement par PHP ; il n'est pas devinable.
