#!/bin/sh
# Contrôle de .env avant make up : fichier présent, variables requises non vides.
#
#   check-env.sh <fichier .env>

fichier=$1
requises='WEB_CONTAINER DB_CONTAINER MAILHOG_CONTAINER DB_USER DB_PASSWORD ADMIN_USER ADMIN_EMAIL ADMIN_PASSWORD'

if [ ! -f "$fichier" ]; then
    echo ".env absent : cp .env.example .env puis le remplir" >&2
    exit 1
fi

vides=
for v in $requises; do
    grep -q "^$v=." "$fichier" || vides="$vides $v"
done

if [ -n "$vides" ]; then
    echo "vides dans .env :$vides" >&2
    exit 1
fi
