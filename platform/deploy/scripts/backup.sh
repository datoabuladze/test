#!/usr/bin/env bash
# Nightly backup: database dump + uploaded files (thumbnails, branding, game packages).
# Keeps 14 daily copies locally; copy BACKUP_DIR off-site (e.g. rclone/restic) for real safety.
set -euo pipefail
BASE="${NEBULO_BASE:-/var/www/nebulo}"
BACKUP_DIR="${NEBULO_BACKUP_DIR:-/var/backups/nebulo}"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
set -a; . "$BASE/shared/.env"; set +a

case "$DB_CONNECTION" in
  pgsql) PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" -Fc "$DB_DATABASE" > "$BACKUP_DIR/db-$STAMP.dump" ;;
  mysql|mariadb) mysqldump --single-transaction --quick -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz" ;;
  *) echo "Unsupported DB_CONNECTION=$DB_CONNECTION"; exit 1 ;;
esac
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" -C "$BASE/shared" storage/app/public game-files
find "$BACKUP_DIR" -type f -mtime +14 -delete
echo "Backup $STAMP complete"
