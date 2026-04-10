#!/usr/bin/env bash
# Seed complet : avatars + users + media + posts
# Usage : bash scripts/seed.sh  ou  make seed
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE="docker compose -f $ROOT/docker-compose.yaml -p camagru"

echo "=== [1/4] Avatars ==="
bash "$SCRIPT_DIR/download_avatars.sh"

echo ""
echo "=== [2/4] Users ==="
$COMPOSE exec -T auth php -r \
    "require '/var/www/html/src/Database.php'; Database::get(); echo 'DB auth OK.' . PHP_EOL;"
$COMPOSE run --rm --no-deps \
    -v "$SCRIPT_DIR/seed_users.php:/seed_users.php:ro" \
    auth php /seed_users.php

echo ""
echo "=== [3/4] Media ==="
$COMPOSE run --rm --no-deps \
    -v "$SCRIPT_DIR/seed_copy_media.php:/seed_copy_media.php:ro" \
    -v "$SCRIPT_DIR/avatars:/avatars:ro" \
    media php /seed_copy_media.php

echo ""
echo "=== [4/4] Posts ==="
$COMPOSE exec -T post php -r \
    "file_get_contents('http://localhost/posts', false, stream_context_create(['http' => ['ignore_errors' => true]])); echo 'DB post OK.' . PHP_EOL;"
$COMPOSE run --rm --no-deps \
    -v "$SCRIPT_DIR/seed_posts.php:/seed_posts.php:ro" \
    -v "$SCRIPT_DIR/avatars/manifest.json:/manifest.json:ro" \
    post php /seed_posts.php

echo ""
echo "=== Seed terminé ==="
