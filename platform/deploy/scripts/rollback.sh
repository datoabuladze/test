#!/usr/bin/env bash
# Points `current` back at the previous release. Database migrations are NOT reversed
# automatically; if the failed release ran a destructive migration, restore from backup.
set -euo pipefail
BASE="${NEBULO_BASE:-/var/www/nebulo}"
CURRENT="$(readlink -f "$BASE/current")"
PREVIOUS="$(ls -1dt "$BASE"/releases/*/ | sed 's#/$##' | grep -vx "$CURRENT" | head -n1)"
[ -n "$PREVIOUS" ] || { echo "No previous release found"; exit 1; }
echo "Rolling back $CURRENT -> $PREVIOUS"
ln -sfn "$PREVIOUS" "$BASE/current.new" && mv -Tf "$BASE/current.new" "$BASE/current"
sudo systemctl reload php8.4-fpm
php "$BASE/current/artisan" queue:restart
php "$BASE/current/artisan" up || true
echo "Rolled back. Investigate $CURRENT before deleting it."
