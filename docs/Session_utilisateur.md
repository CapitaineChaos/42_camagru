# Session utilisateur

## 1 : Principe

Une session PHP associe un identifiant de session côté navigateur à des données stockées côté serveur.

L'état de connexion est stocké dans :

```php
$_SESSION['user']
```

## 2 : Démarrage

La session se démarre au point d'entrée de l'application avec :

```php
session_start();
```

PHP crée ou reprend la session avant le routage, les contrôleurs et les vues.

## 3 : Connexion

Après validation des identifiants dans la BDD :

```php
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'       => (int) $user['id'],
    'username' => $user['username'],
];
```

- `session_regenerate_id(true)` : Remplace l'identifiant de session après authentification.
- `$_SESSION['user']` : Tableau associatif contenant les informations de l'utilisateur connecté.
- `id` : Identifiant stable de l'utilisateur en base.
- `username` : Simple donnée d'affichage.

## 4 : Lecture

L'utilisateur est considéré connecté si :

```php
!empty($_SESSION['user'])
```

Exemple
```php
<?php if (!empty($_SESSION['user'])): ?>
```

Sert à afficher une navigation différente selon l'état connecté/non connecté.

## 5 : Déconnexion

La déconnexion vide les données de session puis détruit la session :

```php
$_SESSION = [];
session_destroy();
```

L'utilisateur ne sera plus authentifié pour les requêtes suivantes.


## 6 : Sécurité

- Régénérer l'identifiant de session après connexion.
- Ne stocker dans `$_SESSION` que les données nécessaires.
- Vérifier `$_SESSION['user']` avant les pages réservées aux utilisateurs connectés.
- Utiliser `POST` pour les actions qui modifient l'état serveur.
