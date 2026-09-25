# Création du squelette from scratch

Point de départ minimal : ce qu'il faut écrire pour qu'une première page
s'affiche. L'état actuel du projet est décrit en fin de document.

## 1 : Structure minimale

L'application vit dans `camagru/`, pas à la racine du dépôt. Seul `public/` est
exposé par le serveur web ; tout le reste est un niveau au-dessus.

```text
camagru/
├── app/
│   ├── Controllers/
│   │   └── HomeController.php
│   ├── Models/
│   │   └── User.php
│   ├── Views/
│   │   ├── home.php
│   │   └── layout.php
│   └── Core/
│       ├── Router.php
│       ├── Controller.php
│       ├── Model.php
│       └── Database.php
├── config/
│   ├── config.php
│   ├── settings.php
│   └── routes.php
├── database/
│   └── schema.sql
└── public/
    ├── css/
    │   └── style.css
    └── index.php
```

| Dossier | Contenu |
|---------|---------|
| `app/Core/` | le socle : routage, classes de base, connexion PDO |
| `app/Controllers/` | un fichier par ensemble de pages |
| `app/Models/` | un fichier par table ou par entité |
| `app/Views/` | les gabarits, plus `layout.php` qui les enveloppe |
| `config/` | `config.php` (déploiement), `settings.php` (comportement), `routes.php` (table des routes) |
| `database/` | le schéma SQL |
| `public/` | `DocumentRoot` : le point d'entrée et les fichiers statiques |

## 2 : Flux d'une requête

```text
1. Navigateur
2. public/index.php          autoloader, session, construction du routeur
3. Router                    résolution méthode + chemin
4. Controller                lecture de l'entrée, décision
5. Model                     accès base, si nécessaire
6. View + layout             rendu HTML
7. Réponse au navigateur
```

- Modèle : les données et les requêtes SQL. Exemple : `User.php`.
- Vue : l'affichage. Exemple : `home.php`, `layout.php`.
- Contrôleur : reçoit la requête, appelle les modèles, choisit la vue. Exemple :
  `HomeController`, `AuthController`.

## 3 : Ce que le projet a ajouté depuis

Le squelette ci-dessus n'est plus l'état du dépôt. Les ajouts :

| Ajout | Contenu |
|-------|---------|
| `app/Core/` étendu | `Session`, `Csrf`, `Flash`, `Mailer`, `Settings`, `Secret`, `Svg`, `Text`, `Pg` |
| `app/Services/` | logique métier hors modèle et hors contrôleur : `Montage`, `Notifications`, `Avatars`, `Overlays`, `CurrentUser`, `LayoutDataProvider` |
| `camagru/storage/` | fichiers écrits par l'application : `avatars/`, `images/` (montages), hors `DocumentRoot` |
| `public/css/` | une douzaine de feuilles par domaine, au lieu de `style.css` |
| `public/js/` | scripts de page : photobooth, galerie, ornements |
| `public/images/` | thème, lettrages SVG |
| `database/admin.php` | création du compte admin depuis les secrets |
| `secrets/` | un credential par fichier, hors dépôt |
| `docker/`, `docker-compose.yml`, `Makefile` | exécution : Apache + PHP, PostgreSQL, MailHog |
| `scripts/` | génération des assets et peuplement de l'instance |
