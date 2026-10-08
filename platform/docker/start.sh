#!/bin/sh
# Preview container start-up: creates the database and demo data on first run, then serves.
set -e
cd "${APP_DIR:-/app}"
[ -n "$APP_KEY" ] || export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
if [ ! -s "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    php artisan migrate --force --seed
fi
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache && php artisan view:cache
echo "Nebulo preview on port $PORT"
exec php -S "0.0.0.0:$PORT" -t public scripts/dev-router.php
