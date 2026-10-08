#!/usr/bin/env bash
# Zero-downtime release deploy. Usage: deploy.sh <git-ref>
# Layout: /var/www/nebulo/{releases/<timestamp>, shared/{.env,storage,public-storage,game-files}, current -> releases/<x>}
# Requires explicit operator approval for production; this script never runs automatically.
set -euo pipefail

REF="${1:?usage: deploy.sh <git-ref>}"
BASE="${NEBULO_BASE:-/var/www/nebulo}"
REPO="${NEBULO_REPO:-git@github.com:datoabuladze/test.git}"
KEEP="${NEBULO_KEEP_RELEASES:-5}"
RELEASE="$BASE/releases/$(date +%Y%m%d%H%M%S)"

echo "==> Building release $RELEASE from $REF"
git clone --quiet --depth 1 --branch "$REF" "$REPO" "$RELEASE.src"
mv "$RELEASE.src/platform" "$RELEASE"
rm -rf "$RELEASE.src"
cd "$RELEASE"

ln -sfn "$BASE/shared/.env" .env
rm -rf storage && ln -sfn "$BASE/shared/storage" storage
mkdir -p "$BASE/shared/game-files" && rm -rf public/game-files && ln -sfn "$BASE/shared/game-files" public/game-files

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

php artisan storage:link --force
php artisan down --retry=15 --refresh=15 || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Switching current -> $RELEASE"
ln -sfn "$RELEASE" "$BASE/current.new" && mv -Tf "$BASE/current.new" "$BASE/current"
sudo systemctl reload php8.4-fpm
php "$BASE/current/artisan" queue:restart
php "$BASE/current/artisan" up

echo "==> Smoke check"
curl -fsS -o /dev/null "$(grep '^APP_URL=' "$BASE/shared/.env" | cut -d= -f2-)/up" || { echo "Health check failed; run rollback.sh"; exit 1; }

echo "==> Pruning old releases (keeping $KEEP)"
ls -1dt "$BASE"/releases/*/ | tail -n +$((KEEP + 1)) | xargs -r rm -rf
echo "Deployed $REF"
