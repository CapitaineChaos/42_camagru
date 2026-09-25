# Architecture générale

Vue d'ensemble du dépôt et de l'exécution.

## 1 : Contenu du dépôt

```text
42_camagru/
├── camagru/              application PHP
│   ├── app/              Controllers, Models, Views, Core, Services
│   ├── config/           config.php, settings.php, routes.php
│   ├── database/         schema.sql, admin.php
│   ├── public/           DocumentRoot : index.php, css, js, images, stickers
│   └── storage/          données écrites : avatars, images (montages)
├── docker/web/           Dockerfile, vhost Apache, uploads.ini
├── docker-compose.yml    web + db + mailhog
├── Makefile              cycle de vie du projet
├── secrets/              un credential par fichier, hors git
├── .env                  configuration de déploiement, hors git
├── assets/               sources de génération : stickers SVG, portraits de seed
├── scripts/              outils Python de génération et de peuplement
├── docs/                 cette documentation
└── composer.json         mapping PSR-4, pour l'IDE et l'analyse statique
```

Le dépôt n'est pas la racine de l'application : `camagru/` l'est. Le reste est de
l'outillage, jamais exposé par le serveur web.

`composer.json` déclare `App\` → `camagru/app/`, mais il n'y a pas de `vendor/` :
le chargement se fait par l'autoloader écrit dans `public/index.php`. Le fichier
sert aux outils qui lisent le mapping, pas à l'exécution.

## 2 : Conteneurs

| Service | Image | Ports | Rôle |
|---------|-------|-------|------|
| `web` | `php:8.3-apache`, étendue par `docker/web/Dockerfile` | 8080 → 80 | Apache + PHP, extensions `pdo_pgsql` et `gd` |
| `db` | `postgres:16-alpine` | 5432 | Base de données |
| `mailhog` | `mailhog/mailhog` | 1025 SMTP, 8025 web | Boîte de réception de développement |

Le Dockerfile aligne l'uid de `www-data` sur celui de l'utilisateur hôte
(`CAMAGRU_UID`/`CAMAGRU_GID` passés par le Makefile), condition pour qu'Apache
puisse écrire dans les dossiers montés depuis l'hôte.

Les mails ne partent nulle part : `MAIL_HOST=mailhog` dans `.env`, tout est lu
sur `http://localhost:8025`.

## 3 : Volumes et données persistantes

| Montage | Type | Contenu |
|---------|------|---------|
| `camagru/{public,app,config,database,storage}` | bind | le code, rechargé sans rebuild |
| `camagru/database/schema.sql` | bind lecture seule | joué par l'entrypoint Postgres à la première initialisation |
| `secrets/` → `/run/secrets` | bind lecture seule | credentials, lus par PHP et par l'entrypoint Postgres |
| `db-data` | volume bind | `$CAMAGRU_DATA/postgres` |
| `images-data` | volume bind | `$CAMAGRU_DATA/images`, les montages |

`CAMAGRU_DATA` vaut `/tmp/42_camagru/.data`.

L'entrypoint de l'image PostgreSQL n'exécute `/docker-entrypoint-initdb.d/` que
si `PGDATA` est vide : il lance `initdb`, puis les scripts. Si le répertoire
contient déjà une base, ils sont ignorés. `schema.sql` n'est donc appliqué
automatiquement qu'à l'initialisation du répertoire de données, pas à chaque
démarrage du conteneur. Ensuite, `make db-apply` (appliquer) ou `make db-reset`
(vider et rejouer). Pour repasser par l'init : `make data-clean`.

## 4 : Exécution depuis /tmp

`make up` ne démarre pas la pile depuis le dépôt : il rsynce le projet vers
`/tmp/42_camagru` et lance `docker compose` depuis là.

Le home est sur NFS, et un processus conteneurisé tournant sous un uid autre que
celui de l'utilisateur ne peut pas lire ce montage. `/tmp` est local, donc
lisible par tous les uid du conteneur.

Conséquence : le code exécuté est la copie, pas le dépôt. `make sync` refait la
copie, `make dev` la refait à chaque modification (`inotifywait`), et
`make watch-db` rejoue le schéma quand `database/` change.

## 5 : Configuration

Trois sources, séparées par nature :

| Source | Contenu | Lecture |
|--------|---------|---------|
| `.env` | déploiement : `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `MAIL_*` | `config/config.php`, via `getenv()` ; injecté par `env_file` dans les conteneurs |
| `secrets/` | credentials : `db_user`, `db_password`, `admin_user`, `admin_email`, `admin_password` | `Core/Secret::read()` |
| `config/settings.php` | comportement : durées de session, bornes de validation, catalogue de stickers | `Settings::get('session.lifetime')` |

Aucun credential dans `.env`, aucun dans le schéma SQL. Le compte admin est créé
par `database/admin.php` (`make admin`) à partir des secrets.

`config.php` charge `.env` quand le fichier est lisible, ce qui couvre les
exécutions CLI depuis l'hôte ; dans les conteneurs les variables sont déjà dans
l'environnement et l'emportent.

## 6 : Chemin d'une requête

```
navigateur → Apache (DocumentRoot = public/, réécriture vers index.php)
           → index.php : config, autoloader, Session::start()
           → Router::dispatch() : CSRF, requireAuth, requireAdmin
           → Controller → Models (PDO) / Services
           → View → layout.php
           → HTML
```

Seul `public/` est exposé. `app/`, `config/`, `database/` et `storage/` sont un
niveau au-dessus du `DocumentRoot`, donc hors d'atteinte d'une URL.

## 7 : Fichiers servis

Les fichiers écrits par l'application ne sont pas dans `public/` :

| Contenu | Emplacement | Accès |
|---------|-------------|-------|
| Montages | `storage/images/` (volume) | route `GET /photo?id=`, `PhotoController` |
| Avatars | `storage/avatars/` | route `GET /avatar`, `AvatarController` |
| Filtres, CSS, JS, images du thème | `public/` | servis directement par Apache |

Passer par un contrôleur permet de vérifier les droits avant d'émettre le
fichier, et de poser les en-têtes (`Content-Type`, `Cache-Control`).

## 8 : Génération des assets

Les images du thème ne sont pas dessinées à la main. Chaîne Python, à lancer
ponctuellement, jamais à l'exécution du site :

| Script | Entrée | Sortie |
|--------|--------|--------|
| `planche_index.py` | `planche.svg` | indexe et mesure chaque glyphe |
| `lettrage.py` | la planche indexée | les SVG de lettrage des menus et titres |
| `vectoriser.py` | PNG en aplats | SVG tracé, une forme par couleur |
| `stickers.py` | `scripts/sources/` | `assets/stickers/*.svg` puis `public/stickers/*.png` |
| `portraits.py` | — | `assets/seed/*.jpg` + `portraits.json` |
| `seed.py` | les portraits | peuple une instance en cours d'exécution, par HTTP |

`stickers.py` rend un PNG en plus du SVG parce que GD ne lit pas le SVG. Les
slugs produits correspondent au catalogue `photobooth.stickers` de
`settings.php`.

`seed.py` n'écrit rien en base : il s'inscrit par HTTP, lit le lien de
confirmation dans MailHog, se connecte, dépose des montages, puis like, commente
et envoie des demandes d'ami.

## 9 : Cycle de vie

| Commande | Effet |
|----------|-------|
| `make secrets` | crée les credentials manquants, demande les identifiants |
| `make up` | vérifie `.env`, rsync vers `/tmp`, construit et démarre |
| `make dev` | `up` + resynchronisation et rejeu du schéma à chaque modification |
| `make sync` | recopie le code vers `/tmp` |
| `make db-apply` / `make db-reset` | applique le schéma / le rejoue à vide, puis recrée l'admin |
| `make admin` | crée ou met à jour le compte admin depuis les secrets |
| `make seed` | peuple l'instance (`ARGS="-n 3"`) |
| `make psql` | ouvre un client SQL sur le conteneur `db` |
| `make clean` / `make fclean` | supprime les volumes / tout, images comprises |

