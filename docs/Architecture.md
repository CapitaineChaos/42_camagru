# Architecture générale

## 1 : Contenu du dépôt

```text
42_camagru/
├── camagru/              application PHP
│   ├── app/              Controllers, Models, Views, Core, Services
│   ├── config/           config.php, settings.php, routes.php
│   ├── database/         schema.sql, admin.sh (initialisation de la base)
│   └── public/           DocumentRoot : index.php, css, js, images, stickers
├── docker/web/           Dockerfile, vhost Apache, uploads.ini
├── docker-compose.yml    web + db + mailhog, code dans l'image
├── docker-compose.override.yml   montages du code pour le développement
├── docker-compose.podman.yml     réglages podman rootless, ajoutés par le Makefile sous podman
├── .dockerignore         ce que le build de web peut copier
├── Makefile              cycle de vie du projet
├── .env                  configuration et credentials, hors git (modèle : .env.example)
├── assets/               sources : stickers SVG, portraits de seed, planche de glyphes, fichiers GIMP
├── scripts/              outils Python de génération et de peuplement
└── docs/                 cette documentation
```

La racine de l'application est `camagru/`. Le reste du dépôt est de
l'outillage, que le serveur web n'expose pas. `storage/` n'existe que dans le
conteneur : le Dockerfile le crée, et les volumes `images-data` et
`avatars-data` y sont montés.

## 2 : Configuration

Deux sources :

| Source | Contenu | Lecture |
|--------|---------|---------|
| `.env` | déploiement (`APP_URL`, noms des conteneurs `*_CONTAINER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `MAIL_*`) et credentials (`DB_USER`, `DB_PASSWORD`, `ADMIN_*`) | `config/config.php`, via `getenv()` ; injecté par `env_file` dans `web`, interpolé par compose pour `db` |
| `config/settings.php` | comportement : durées de session, bornes de validation, catalogue de stickers | `Settings::get('session.lifetime')` |

Aucun credential dans le dépôt : `.env.example` les laisse vides, et le schéma
SQL n'en contient aucun. Le compte admin est créé à l'initialisation de la base
par `database/admin.sh`, à partir de `ADMIN_*`.

`config.php` lit les variables de `.env` dans l'environnement du conteneur, où
`env_file` les a placées ; une variable absente ou vide arrête le démarrage. PHP
ne s'exécute que dans les conteneurs.

## 3 : Chemin d'une requête

```
navigateur → Apache (DocumentRoot = public/, réécriture vers index.php)
           → index.php : config, autoloader, Session::start()
           → Router::dispatch() : CSRF, route, AUTH, ADMIN
           → Controller → Models (PDO) / Services
           → View → layout.php
           → HTML
```

Seul `public/` est exposé. `app/`, `config/`, `database/` et `storage/` sont un
niveau au-dessus du `DocumentRoot`, donc hors d'atteinte d'une URL.

## 4 : Fichiers servis

Les fichiers écrits par l'application ne sont pas dans `public/` :

| Contenu | Emplacement | Accès |
|---------|-------------|-------|
| Montages | `storage/images/` (volume `images-data`) | route `GET /photo?id=`, `PhotoController` |
| Avatars | `storage/avatars/` | route `GET /avatar`, `AvatarController` |
| Filtres, CSS, JS, images du thème | `public/` | servis directement par Apache |

Passer par un contrôleur permet de vérifier les droits avant d'émettre le
fichier, et de poser les en-têtes (`Content-Type`, `Cache-Control`).

## 5 : Génération des assets

Les images du thème sont produites par les scripts Python de `scripts/draw/`,
lancés à la main et jamais pendant l'exécution du site, avec le Python du venv
(`scripts/.venv/bin/python`). Le venv est créé une fois, par `make seed` ou
`make venv`, à partir de `scripts/requirements.txt`, et n'est réinstallé que si
ce fichier change.

| Script | Entrée | Sortie |
|--------|--------|--------|
| `planche_index.py` | `assets/elements/planche.svg` | indexe et mesure chaque glyphe |
| `lettrage.py` | la planche indexée | les SVG de lettrage des menus et titres |
| `vectoriser.py` | PNG en aplats | SVG tracé, une forme par couleur |
| `stickers.py` | `scripts/draw/sources/` | `assets/stickers/*.svg` puis `public/stickers/*.png` |
| `portraits.py` | — | `assets/seed/*.jpg` + `portraits.json` |
| `seed.py` | les portraits | peuple une instance en cours d'exécution, par HTTP |

`stickers.py` rend un PNG en plus du SVG parce que GD ne lit pas le SVG. Les
slugs produits correspondent au catalogue `photobooth.stickers` de
`settings.php`.

`seed.py` n'écrit rien en base : il s'inscrit par HTTP, lit le lien de
confirmation dans MailHog, se connecte, dépose des montages, puis like, commente
et envoie des demandes d'ami.

## 6 : Cycle de vie

| Commande | Effet |
|----------|-------|
| `make up` | vérifie `.env`, reconstruit l'image et recrée les conteneurs, code dans l'image |
| `make dev` | idem, avec le code monté depuis le dépôt (`docker-compose.override.yml`) |
| `make watch-apache` | suit les logs du conteneur `web`, erreurs et accès colorés |
| `make watch-db` | rejoue `schema.sql` dans `db` à chaque modification de `database/` |
| `make seed` | peuple l'instance (`ARGS="-n 3"`) |
| `make psql` | ouvre un client SQL sur le conteneur `db` |
| `make clean` / `make fclean` | supprime les données / tout, images comprises |

