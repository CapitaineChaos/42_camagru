# Camagru

Application web de montages photo : capture par webcam ou envoi d'image,
superposition de stickers côté serveur, galerie publique avec likes et
commentaires. PHP 8.3 sans framework, PostgreSQL 16, Apache, MailHog pour les
mails.

## Prérequis

- podman et podman-compose : `docker-compose.yml` utilise `userns_mode: keep-id`,
  propre à podman ;
- make ;
- python3, pour `make secrets` et les scripts de `scripts/`.

## Lancement

```sh
cp .env.example .env
make up
```

Au premier lancement, `make up` demande le rôle PostgreSQL et le compte admin,
puis tire les mots de passe ; les valeurs sont écrites dans `secrets/`, hors
git.

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
