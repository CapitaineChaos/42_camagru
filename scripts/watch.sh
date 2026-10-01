#!/bin/sh
# Suivi en continu, lancé par make watch-apache et make watch-db.
#
#   watch.sh --apache <conteneur>          logs du conteneur, erreurs et accès colorés
#   watch.sh --db <conteneur> <dossier>    rejoue <dossier>/schema.sql dans le
#                                          conteneur postgres à chaque modification
#
# Ctrl-C arrête le suivi ; le trap sort en 0, ce qui évite le « Error 130 » de make.

usage() {
    echo "usage: $0 --apache <conteneur> | --db <conteneur> <dossier>" >&2
    exit 1
}

# Colore les logs Apache champ par champ, pour repérer le début de chaque entrée
# quand les lignes reviennent à la ligne :
#   journal d'erreurs  [date] [module:niveau] [pid …] [client …] message
#   journal d'accès    ip - - [date] "requête" statut taille "referer" "agent"
# date en cyan ; niveau et statut en vert (normal), bleu (3xx), orange (warn,
# 4xx, avertissement PHP), rouge (error et pire, 5xx, erreur fatale PHP) ;
# niveau et statut en gras ; pid, client, ip, referer et agent en gris. Les
# autres lignes passent telles quelles. sed -u écrit ligne par ligne au lieu d'attendre 4 Ko.
colorer() {
    e=$(printf '\033')
    r="$e[0m" gris="$e[90m" cyan="$e[36m" gras="$e[1m"
    vert="$e[32m" bleu="$e[34m" orange="$e[38;5;208m" rouge="$e[31m"
    acces='^([0-9a-fA-F.:]+ \S+ \S+) (\[[^]]*\]) ("[^"]*")'
    sed -u -E \
        -e "s/^\[[^]]*\]/$cyan&$r/" \
        -e "s/\[[a-z_0-9]+:(emerg|alert|crit|error)\]/$gras$rouge&$r/" \
        -e "s/\[[a-z_0-9]+:warn\]/$gras$orange&$r/" \
        -e "s/\[[a-z_0-9]+:(notice|info)\]/$gras$vert&$r/" \
        -e "s/\[(pid|client) [^]]*\]/$gris&$r/g" \
        -e "s/PHP (Fatal error|Parse error|Recoverable fatal error):.*/$rouge&$r/" \
        -e "s/PHP (Warning|Notice|Deprecated):.*/$orange&$r/" \
        -e "s/$acces (2[0-9]{2}) (.*)/$gris\1$r $cyan\2$r $gras\3$r $gras$vert\4$r $gris\5$r/" \
        -e "s/$acces (3[0-9]{2}) (.*)/$gris\1$r $cyan\2$r $gras\3$r $gras$bleu\4$r $gris\5$r/" \
        -e "s/$acces (4[0-9]{2}) (.*)/$gris\1$r $cyan\2$r $gras\3$r $gras$orange\4$r $gris\5$r/" \
        -e "s/$acces (5[0-9]{2}) (.*)/$gris\1$r $cyan\2$r $gras\3$r $gras$rouge\4$r $gris\5$r/"
}

trap 'exit 0' INT TERM

case "$1" in
--apache)
    [ $# -eq 2 ] || usage
    docker container inspect "$2" >/dev/null 2>&1 \
        || { echo "[watch-apache] conteneur $2 introuvable : make up d'abord" >&2; exit 1; }
    # docker logs plutôt que compose logs : podman-compose affiche une trace
    # Python à chaque Ctrl-C
    docker logs -f "$2" 2>&1 | colorer
    # atteint seulement si les logs se ferment d'eux-mêmes (conteneur arrêté ou
    # recréé par make up) ; Ctrl-C sort avant, par le trap
    echo "[watch-apache] fin des logs : le conteneur $2 s'est arrêté" >&2
    exit 1
    ;;
--db)
    [ $# -eq 3 ] || usage
    # le dossier plutôt que le fichier : un éditeur qui enregistre par renommage
    # remplace le fichier, et inotifywait perdrait sa cible. Le rôle et la base
    # sont lus dans le conteneur : POSTGRES_USER et POSTGRES_DB, venus de .env.
    command -v inotifywait >/dev/null \
        || { echo "[watch-db] inotifywait absent (paquet inotify-tools)" >&2; exit 1; }
    [ -f "$3/schema.sql" ] || { echo "[watch-db] $3/schema.sql introuvable" >&2; exit 1; }
    echo "[watch-db] Surveillance de $3 active. Ctrl-C pour arrêter."
    while :; do
        inotifywait -r -q -e close_write,move,create,delete "$3" >/dev/null \
            || { echo "[watch-db] inotifywait a échoué sur $3" >&2; exit 1; }
        # ON_ERROR_STOP : la première erreur SQL arrête psql en code non nul ;
        # sans lui, psql continue et sort en 0. Un échec est signalé, la
        # surveillance continue pour la prochaine correction.
        if docker exec -i "$2" sh -c 'psql -q -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$3/schema.sql"; then
            echo "[watch-db] schema.sql rejoué $(date +%H:%M:%S)"
        else
            echo "[watch-db] ÉCHEC du rejeu de schema.sql $(date +%H:%M:%S), erreur ci-dessus" >&2
        fi
    done
    ;;
*)
    usage
    ;;
esac
