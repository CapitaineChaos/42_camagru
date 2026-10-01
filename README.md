# Camagru

Application web de montages photo : capture par webcam ou envoi d'image,
superposition de stickers côté serveur, galerie publique avec likes et
commentaires. PHP 8.3 sans framework, PostgreSQL 16, Apache, MailHog pour les
mails.

## Prérequis

- Docker et docker compose, ou podman et podman-compose ; sous podman, le
  Makefile ajoute `docker-compose.podman.yml` (`userns_mode: keep-id`) ;
- make ;
- python3, pour `make seed`.

## Lancement

```sh
cp .env.example .env
make up
```

Avant `make up`, remplir dans `.env` le rôle PostgreSQL (`DB_USER`,
`DB_PASSWORD`) et le compte admin (`ADMIN_USER`, `ADMIN_EMAIL`,
`ADMIN_PASSWORD`). `make up` refuse de démarrer tant qu'une de ces valeurs est
vide. `.env` est ignoré par git.

| Service | Adresse |
|---------|---------|
| Camagru | http://localhost:8080/ |
| MailHog (mails envoyés) | http://localhost:8025/ |

## Commandes

| Commande | Effet |
|----------|-------|
| `make up` | construit l'image et démarre, code copié dans l'image |
| `make dev` | idem, code monté depuis le dépôt, puis rejeu de `schema.sql` à chaque modification |
| `make down` | arrête et supprime les conteneurs ; les données restent |
| `make clean` | `down`, plus suppression des données (volumes) |
| `make fclean` | `clean`, plus suppression des images |
| `make watch-apache` | logs d'Apache et de PHP, colorés |
| `make psql` | client SQL dans le conteneur `db` |
| `make seed` | peuple l'instance par HTTP (`ARGS="-n 3"`) |
