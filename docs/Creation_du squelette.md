# Création du squelette

Fichiers minimaux pour afficher une première page. La section 3 décrit les
ajouts présents dans l'état actuel du dépôt.

## 1 : Structure minimale

L'application se trouve dans le sous-dossier `camagru/` du dépôt. Seul
`public/` est exposé par le serveur web ; le reste est un niveau au-dessus.

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
| `app/Core/` | routage, classes de base, connexion PDO |
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



## 3 : Ajouts ultérieurs

Ajouts au squelette dans l'état actuel du dépôt :

| Ajout | Contenu |
|-------|---------|
| `app/Core/` étendu | `Session`, `Csrf`, `Flash`, `Mailer`, `Settings`, `Svg`, `Text`, `Pg` |
| `app/Services/` | logique métier hors modèle et hors contrôleur : `Montage`, `Notifications`, `Avatars`, `Overlays`, `CurrentUser` |
| `storage/` (conteneur) | fichiers écrits par l'application : `avatars/`, `images/` (montages), hors `DocumentRoot` ; créé par le Dockerfile, monté sur des volumes nommés |
| `public/css/` | une douzaine de feuilles par domaine, qui remplacent `style.css` |
| `public/js/` | scripts de page : photobooth, galerie, ornements |
| `public/images/` | thème, lettrages SVG |
| `database/admin.sh` | création du compte admin à l'initialisation de la base, depuis `.env` (`ADMIN_*`) |
| `docker/`, `docker-compose.yml`, `Makefile` | exécution : Apache + PHP, PostgreSQL, MailHog |
| `scripts/` | génération des assets et peuplement de l'instance |
