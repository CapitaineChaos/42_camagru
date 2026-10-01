#!/bin/sh
# Crée ou met à jour le compte admin (database/admin.php) dans le conteneur web,
# une fois postgres prêt. Lancé par make up et make admin.
#
#   admin.sh <conteneur web> <conteneur db>
set -e

[ $# -eq 2 ] || { echo "usage: $0 <conteneur web> <conteneur db>" >&2; exit 1; }

# up -d rend la main avant que postgres accepte les connexions : jusqu'à 30 s
# d'attente. -h 127.0.0.1 ignore le serveur temporaire de l'initialisation,
# qui n'écoute que sur le socket Unix.
i=0
until docker exec "$2" pg_isready -q -h 127.0.0.1 2>/dev/null; do
    i=$((i + 1))
    [ "$i" -lt 30 ] || { echo "[admin] postgres ne répond pas après 30 s : docker logs $2" >&2; exit 1; }
    sleep 1
done

# sous www-data, aligné sur l'uid hôte : le root du conteneur, avec keep-id,
# ne lit pas le NFS
docker exec -u www-data "$1" php database/admin.php
