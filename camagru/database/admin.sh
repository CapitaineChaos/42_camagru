#!/bin/sh
# Compte admin Camagru, créé une seule fois, à l'initialisation de la base, par
# l'entrypoint postgres (après le schéma), depuis ADMIN_* de .env.
# pgcrypto hache le mot de passe en bcrypt ($2a$, coût 10) : password_verify le
# vérifie comme un hash de password_hash.
set -e

psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
     -v nom="$ADMIN_USER" -v adresse="$ADMIN_EMAIL" -v motdepasse="$ADMIN_PASSWORD" <<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;

WITH compte AS (
    INSERT INTO users (username, email, password, avatar, modele, verified)
    VALUES (:'nom', :'adresse', crypt(:'motdepasse', gen_salt('bf', 10)), 'generique.png', TRUE, TRUE)
    RETURNING id
)
INSERT INTO admins (user_id) SELECT id FROM compte;
SQL
