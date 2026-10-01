# Architecture générale

## 1 : Contenu du dépôt

```text
42_camagru/
├── camagru/              application PHP
│   ├── app/              Controllers, Models, Views, Core, Services
│   ├── config/           config.php, settings.php, routes.php
│   ├── database/         schema.sql, admin.php
│   └── public/           DocumentRoot : index.php, css, js, images, stickers
├── docker/web/           Dockerfile, vhost Apache, uploads.ini
├── docker-compose.yml    web + db + mailhog, code dans l'image
├── docker-compose.override.yml   montages du code pour le développement
├── .dockerignore         ce que le build de web peut copier
├── Makefile              cycle de vie du projet
├── secrets/              un credential par fichier, hors git
├── .env                  configuration de déploiement, hors git
├── assets/               sources : stickers SVG, portraits de seed, planche de glyphes, fichiers GIMP
├── scripts/              outils Python de génération et de peuplement
├── docs/                 cette documentation
└── composer.json         mapping PSR-4, pour l'IDE et l'analyse statique
```

La racine de l'application est `camagru/`. Le reste du dépôt est de
l'outillage, que le serveur web n'expose pas. `storage/` n'existe que dans le
conteneur : le Dockerfile le crée, et les volumes `images-data` et
`avatars-data` y sont montés.

`composer.json` déclare `App\` → `camagru/app/`, mais il n'y a pas de `vendor/` :
le chargement se fait par l'autoloader écrit dans `public/index.php`. Le fichier
ne sert qu'aux outils qui lisent le mapping.

## 2 : Conteneurs

| Service | Image | Ports | Rôle |
|---------|-------|-------|------|
| `web` | `php:8.3-apache`, étendue par `docker/web/Dockerfile` | 8080 → 80 | Apache + PHP, extensions `pdo_pgsql` et `gd` |
| `db` | `postgres:16-alpine` | 5432, avec `make dev` seulement | Base de données |
| `mailhog` | `mailhog/mailhog` | 1025 SMTP, 8025 web | Boîte de réception de développement |

`web` et `db` tournent en `userns_mode: keep-id` : l'uid de l'utilisateur hôte
est le même dans le conteneur. Sous podman rootless, tout autre uid devient un
subuid, que le serveur NFS des postes 42 refuse. PostgreSQL tourne directement
sous cet uid. Le maître Apache démarre en root pour écouter sur le port 80, et
ses workers passent sous `www-data`, dont le Dockerfile aligne l'uid sur
`CAMAGRU_UID`/`CAMAGRU_GID` (passés par le Makefile). Le code et les données se
lisent et s'écrivent donc sous l'uid hôte, NFS compris. Un pod podman ne peut pas
porter `keep-id` : le compose désactive les pods (`x-podman: in_pod: false`).
`keep-id` est propre à podman ; Docker refuse cette valeur.

Les mails restent dans MailHog (`MAIL_HOST=mailhog` dans `.env`) et se lisent
sur `http://localhost:8025`.

## 3 : Code, volumes et secrets

`docker-compose.yml` décrit le déploiement. Le Dockerfile de `web` copie
`camagru/{public,app,config,database}` dans l'image ; le contexte de build est
la racine du dépôt, et `.dockerignore` n'y laisse passer que ces dossiers et
`docker/web/`, si bien que `secrets/` et `.env` n'entrent jamais dans l'image.
Une modification du code demande donc une reconstruction (`make up`) ; seules
les couches de copie du code sont refaites.

`docker-compose.override.yml` sert au développement (`make dev`) : il monte ces
quatre dossiers depuis le dépôt par-dessus ceux de l'image, et une modification
est visible sans reconstruire. Il publie aussi le port 5432 de `db` sur l'hôte.
`make up` passe `-f docker-compose.yml` seul et ignore ce fichier.

| Montage | Type | Contenu |
|---------|------|---------|
| `db-data` | volume nommé | données PostgreSQL |
| `images-data` → `storage/images` | volume nommé | les montages |
| `avatars-data` → `storage/avatars` | volume nommé | les avatars découpés dans un montage |
| `camagru/database/schema.sql` | bind lecture seule | joué par l'entrypoint Postgres à la première initialisation |
| `secrets:` → `/run/secrets/<nom>` | secrets compose, lecture seule | `web` reçoit les cinq fichiers, `db` seulement `db_user` et `db_password` |

Les volumes nommés sont gérés par podman, sous `/goinfre/$USER/containers/volumes`
(disque local), et préfixés du nom de projet : `camagru_db-data`,
`camagru_images-data`, `camagru_avatars-data`. `make clean` et `make fclean` les
suppriment.

Un volume neuf reprend le propriétaire et les droits du point de montage dans
l'image. Pour `db-data`, c'est l'uid 70 en 1777 : PostgreSQL, qui tourne sous
l'uid hôte, ne peut pas en changer les droits, et `initdb` échoue sur la racine
du volume. `PGDATA` pointe donc sur un sous-dossier, `data/pgdata`, que
l'entrypoint crée et qui appartient à l'uid hôte. podman-compose ne transmet pas
l'option `:U`, qui aurait réglé le propriétaire du volume. `images-data` et
`avatars-data` reprennent les droits des points de montage créés dans le
Dockerfile, en 1777 : un `chown` vers l'uid hôte échoue pendant la construction
de l'image, qui tourne dans un espace de noms où cet uid n'existe pas.

L'entrypoint de l'image PostgreSQL n'exécute `/docker-entrypoint-initdb.d/` que
si `PGDATA` est vide : il lance `initdb`, puis les scripts. Si le répertoire
contient déjà une base, ils sont ignorés. `schema.sql` n'est donc appliqué
automatiquement qu'à l'initialisation du répertoire de données, pas à chaque
démarrage du conteneur. Sur une base existante, `make watch-db` (lancé aussi
par `make dev`) rejoue `schema.sql` à chaque modification de `database/`. Pour
repasser par
l'initialisation : `make clean`, qui supprime les volumes.

## 4 : Configuration

Trois sources :

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

## 5 : Chemin d'une requête

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

## 6 : Fichiers servis

Les fichiers écrits par l'application ne sont pas dans `public/` :

| Contenu | Emplacement | Accès |
|---------|-------------|-------|
| Montages | `storage/images/` (volume `images-data`) | route `GET /photo?id=`, `PhotoController` |
| Avatars | `storage/avatars/` | route `GET /avatar`, `AvatarController` |
| Filtres, CSS, JS, images du thème | `public/` | servis directement par Apache |

Passer par un contrôleur permet de vérifier les droits avant d'émettre le
fichier, et de poser les en-têtes (`Content-Type`, `Cache-Control`).

## 7 : Génération des assets

Les images du thème sont produites par des scripts Python, lancés
ponctuellement et jamais pendant l'exécution du site :

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

## 8 : Cycle de vie

| Commande | Effet |
|----------|-------|
| `make secrets` | crée les credentials manquants, demande les identifiants |
| `make up` | vérifie `.env`, construit et démarre avec `docker-compose.yml` seul |
| `make dev` | idem avec `docker-compose.override.yml` (code monté depuis le dépôt), puis rejeu du schéma à chaque modification de `database/` |
| `make watch-apache` | suit les logs du conteneur `web`, erreurs et accès colorés |
| `make watch-db` | rejoue `schema.sql` dans `db` à chaque modification de `database/` |
| `make admin` | crée ou met à jour le compte admin depuis les secrets |
| `make seed` | peuple l'instance (`ARGS="-n 3"`) |
| `make psql` | ouvre un client SQL sur le conteneur `db` |
| `make clean` / `make fclean` | supprime les données / tout, images comprises |

