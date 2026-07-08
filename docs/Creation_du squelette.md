# Création du squelette from scratch

## 1 : Structure du projet

- Application : `app/`
  - Modèles : `Models/`
    - User : `User.php`
  - Vues : `Views/`
    - layout : `layout.php`
    - home : `home.php`
  - Contrôleurs : `Controllers/`
  - Core : `Core/`
    - Database : `Database.php`
    - Controller : `Controller.php`
    - Model : `Model.php`
    - Router : `Router.php`
- configuration : `config/`
  - routes : `routes.php`
  - config : `config.php`
- data : `database/`
  - schema : `schema.sql`
- public : `public/`
  - css : `css/`
    - style : `style.css`
  - index : `index.php`


```text
  ├── app/
  │   ├── Controllers/
  │   ├── Models/
  │       └── User.php
  │   ├── Views/
  │   │   ├── home.php
  │   │   └── layout.php
  │   └── Core/
  │       ├── Database.php
  │       ├── Controller.php
  │       ├── Model.php
  │       └── Router.php
  ├── config/
  │   ├── config.php
  │   └── routes.php
  ├── database/
  │   └── schema.sql
  └── public/
      ├── css/
      │   └── style.css
      └── index.php
```

## 2 : Pattern d'architecture MVC

Flux de l'application :

```text

1. Navigateur
   
2. public/index.php

3. Router

4. Controller

5. Model si besoin

6. View + layout

7. HTML renvoyé au navigateur

```

- Model : gère les données et la logique liée aux données
Exemple : User.php, requêtes SQL, récupération d’un utilisateur.

- View : gère l’affichage
Exemple : home.php, layout.php, preferences.php.

- Controller : reçoit la requête, appelle les modèles si besoin, puis choisit quelle vue afficher
Exemple : HomeController, AuthController, PrefsController.