# Docker et podman

## 1 : Services

| Service | Image | Ports | Rôle |
|---------|-------|-------|------|
| `web` | `php:8.3-apache`, étendue par `docker/web/Dockerfile` | 8080 → 80 | Apache et PHP, extensions `pdo_pgsql` et `gd` |
| `db` | `postgres:16-alpine` | 5432, en mode développement seulement | base de données |
| `mailhog` | `mailhog/mailhog` | 1025 (SMTP), 8025 (interface web) | serveur SMTP de test |

Les noms des conteneurs viennent de `.env` (`WEB_CONTAINER`, `DB_CONTAINER`,
`MAILHOG_CONTAINER`).

## 2 : Fichiers compose

| Fichier | Lu par | Contenu |
|---------|--------|---------|
| `docker-compose.yml` | toutes les commandes | déploiement : code dans l'image, données dans des volumes nommés |
| `docker-compose.override.yml` | `make dev` | montages du code depuis le dépôt, port 5432 de `db` publié |
| `docker-compose.podman.yml` | toutes les commandes, sous podman | réglages propres à podman rootless |

Le Makefile passe les fichiers explicitement (`-f`). Il ajoute
`docker-compose.podman.yml` quand `docker --version` mentionne podman, et
`docker-compose.override.yml` pour `make dev`.

## 3 : Image de `web`

Le contexte de build est la racine du dépôt. `.dockerignore` n'y laisse que
`docker/web/` et les dossiers copiés par le Dockerfile : `camagru/public`,
`camagru/app`, `camagru/config`. `.env` n'entre jamais dans l'image.

Le Dockerfile installe les extensions, active les modules Apache `rewrite` et
`headers`, copie le vhost et `uploads.ini`, puis copie le code en dernier. Une
modification du code ne refait que ces dernières couches ; l'installation et la
compilation de GD sont reprises du cache.

`make up` passe `--build --force-recreate` : l'image est reconstruite, puis tous
les conteneurs sont recréés. Sans `--force-recreate`, podman-compose relance un
conteneur existant tant que sa configuration compose n'a pas changé, même si son
image a été reconstruite.

En mode développement, l'override monte `camagru/public`, `camagru/app` et
`camagru/config` par-dessus les copies de l'image : une modification est visible
au rechargement de la page.

## 4 : Volumes

| Volume | Monté sur | Contenu |
|--------|-----------|---------|
| `db-data` | `/var/lib/postgresql/data` (`db`) | données PostgreSQL |
| `images-data` | `/var/www/html/storage/images` (`web`) | montages |
| `avatars-data` | `/var/www/html/storage/avatars` (`web`) | avatars découpés dans un montage |

Les volumes portent le préfixe du projet (`camagru_db-data`…). `make down`
supprime les conteneurs et garde les volumes ; `make clean` supprime aussi les
volumes ; `make fclean` supprime en plus les images.

`db` monte en lecture seule deux fichiers du dépôt dans
`/docker-entrypoint-initdb.d/` : `schema.sql` sous le nom `10-schema.sql`, puis
`admin.sh` sous le nom `20-admin.sh`. L'entrypoint de l'image PostgreSQL les
exécute dans l'ordre alphabétique, une seule fois, quand le répertoire de données
est vide.

## 5 : Podman rootless

Sous podman rootless, un uid du conteneur autre que 0 correspond sur l'hôte à
un uid de la plage réservée à l'utilisateur (subuid). Le serveur NFS des postes
de 42 refuse ces uid : un processus qui tourne sous `www-data` ou `postgres` ne
peut pas lire un fichier du dépôt monté depuis le NFS.

`docker-compose.podman.yml` règle ce cas :

| Réglage | Effet |
|---------|-------|
| `userns_mode: keep-id` sur `web` et `db` | l'uid de l'utilisateur hôte garde la même valeur dans le conteneur |
| `x-podman: in_pod: false` | conteneurs hors pod, condition de `keep-id` |
| `user: root` sur `web` | le processus principal d'Apache démarre en root pour ouvrir le port 80 ; les processus qui exécutent PHP passent sous `www-data` |
| `PGDATA: /var/lib/postgresql/data/pgdata` | PostgreSQL crée sa base dans un sous-dossier du volume, qui lui appartient |

Le Dockerfile donne à `www-data` l'uid et le gid de l'utilisateur
(`CAMAGRU_UID`, `CAMAGRU_GID`, exportés par le Makefile depuis `id -u` et
`id -g`) : PHP lit et écrit les fichiers sous l'identité de l'utilisateur.

Un volume neuf prend le propriétaire et les droits de son point de montage dans
l'image. Pour `db-data`, l'image fournit un dossier de l'uid 70 en `1777` ;
PostgreSQL, sous l'uid de l'utilisateur, ne peut pas en changer les droits, d'où
le sous-dossier `PGDATA`. Pour `images-data` et `avatars-data`, le Dockerfile
crée les points de montage en `1777` : le build ne peut pas les attribuer à
l'uid de l'utilisateur, absent de l'espace de noms de construction.

Docker refuse `userns_mode: keep-id` ; avec Docker, `docker-compose.podman.yml`
n'est pas lu et ces réglages ne s'appliquent pas.
