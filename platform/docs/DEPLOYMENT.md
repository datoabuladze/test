# Deployment

> **Status: no production deployment has been performed.** Everything in `platform/deploy/` is an untested template. Deploying requires the owner's explicit approval, a registered domain (plus a second domain for game files), server access and production credentials (database, mail, Redis). None of these exist in the repository. The nginx configuration has **not** been validated with `nginx -t` in this repository.

This document describes the intended production setup, based on the files in `platform/deploy/`:

| File | Installs to |
|---|---|
| `deploy/nginx/nebulo.conf` | `/etc/nginx/sites-available/nebulo.conf` (two server blocks + HTTP redirect) |
| `deploy/nginx/nebulo-game-files.conf` | `/etc/nginx/snippets/nebulo-game-files.conf` |
| `deploy/php/nebulo-fpm.conf` | `/etc/php/8.4/fpm/pool.d/nebulo.conf` |
| `deploy/supervisor/nebulo-worker.conf` | `/etc/supervisor/conf.d/nebulo-worker.conf` |
| `deploy/cron/nebulo` | `/etc/cron.d/nebulo` |
| `deploy/scripts/deploy.sh`, `rollback.sh`, `backup.sh` | Run from the release (`/var/www/nebulo/current/deploy/scripts/`) |
| `deploy/env.production.example` | Template for `/var/www/nebulo/shared/.env` |

## Server prerequisites

- Linux server with nginx, PHP 8.4 FPM (`gd` with WebP, `zip`, `intl`, `mbstring`, `pdo_pgsql` or `pdo_mysql`, `redis` / phpredis), Composer, Node 22 + npm, git, supervisor, cron.
- PostgreSQL (the production example uses `pgsql`) or MariaDB.
- Redis (sessions, cache, queue in the production example).
- TLS certificates for both hostnames (the config expects Let's Encrypt paths).
- A system user `nebulo` (group `www-data`) that owns `/var/www/nebulo`. `deploy.sh` and `rollback.sh` call `sudo systemctl reload php8.4-fpm`, so that user needs a sudoers rule for exactly that command.
- For `games:smoke` in the scheduler: Node, the Playwright package and a Chromium binary (`CHROMIUM_PATH`). If they are missing, the daily smoke run fails without changing game status.

## Directory layout

```
/var/www/nebulo/
  releases/
    20261008120000/          # one directory per deploy (contents of repo's platform/)
    ...
  shared/
    .env                     # production environment (never committed)
    storage/                 # Laravel storage (logs, app/public uploads, framework cache)
    game-files/              # uploaded game packages (public/game-files symlinks here)
  current -> releases/<latest>
```

Each release symlinks `.env`, `storage/` and `public/game-files/` into `shared/`, so uploads and logs survive deploys. Note: the header comment of `deploy.sh` also lists `shared/public-storage`, but the script never creates or uses it; `public/storage` is created by `php artisan storage:link` pointing into `shared/storage/app/public`.

Prepare it once:

```bash
sudo mkdir -p /var/www/nebulo/{releases,shared/storage,shared/game-files}
sudo chown -R nebulo:www-data /var/www/nebulo
# Laravel storage skeleton
mkdir -p /var/www/nebulo/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
cp deploy/env.production.example /var/www/nebulo/shared/.env   # then edit it
chmod 640 /var/www/nebulo/shared/.env
```

## Environment (`shared/.env`)

Start from `deploy/env.production.example`. Values that must be set before launch:

| Variable | Notes |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `APP_URL` | Canonical HTTPS URL, e.g. `https://www.example.com` |
| `APP_ENV=production`, `APP_DEBUG=false` | robots.txt only allows indexing when `APP_ENV=production` |
| `DB_*` | `pgsql` (port 5432) or `mariadb` (port 3306) |
| `REDIS_*` | Sessions, cache, queue |
| `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` | Already in the example |
| `MAIL_*` | Real SMTP; needed for email verification and password reset |
| `GAMES_ORIGIN` | Separate registrable domain for game files, e.g. `https://play.example-games.net` |
| `VISITOR_HASH_SALT` | Long random string; defaults to `APP_KEY` if empty |
| `LEGAL_OPERATOR`, `CONTACT_EMAIL`, `LEGAL_JURISDICTION` | Rendered into legal pages; `CONTACT_EMAIL` receives takedown and security reports |
| `TRUSTED_PROXIES` | IPs/CIDRs of a CDN or load balancer in front of nginx; otherwise keep `127.0.0.1` |
| `GA4_MEASUREMENT_ID`, `GOOGLE_SITE_VERIFICATION` | Optional |
| `ADSENSE_CLIENT` | Set **only after AdSense approval**; see the CMP note in `docs/SECURITY.md` |
| `REDIS_QUEUE_RETRY_AFTER` | `1900` in the example (also the default in `config/queue.php`). Must stay above the longest job timeout (imports: 1800 s), or a second worker can pick up a still-running import |

`MAX_GAME_PACKAGE_KB` (default 204800 = 200 MB) must stay below nginx `client_max_body_size` (210m) and the FPM `upload_max_filesize`/`post_max_size` (210M).

## nginx

`deploy/nginx/nebulo.conf` defines:

1. **HTTP -> HTTPS redirect** for `example.com` and `www.example.com`.
2. **App server** (`www.example.com`): root `current/public`; long-lived caching for `/build/`; `/storage/` with `nosniff` and PHP disabled; game paths (`/games/`, `/game-files/`, `/vendor/ruffle/`) via the snippet (kept so games still work when `GAMES_ORIGIN` is empty); `/api/` rate-limited at 20 req/s per IP (burst 40) in addition to Laravel's limiters; only `index.php` is executed (`fastcgi_pass unix:/run/php/php8.4-fpm-nebulo.sock`); dotfiles denied; `client_max_body_size 210m`.
3. **Games origin** (`play.example-games.net`): same `public/` root, serves only the game paths (through the snippet) and `/frame/*` (routed to Laravel for the Ruffle/Unity wrapper pages and the redirects for HTML5 games); everything else returns 404. This block has no port-80 redirect and no `ssl_protocols` line; add them if needed.

`nebulo-game-files.conf` sets `Access-Control-Allow-Origin: *` (sandboxed frames have an opaque origin), `Cross-Origin-Resource-Policy: cross-origin`, `nosniff`, a 1-day cache, refuses script extensions, relies on the stock `mime.types` (no `types {}` block, which would replace it), and for Unity `.br`/`.gz` files sets `Content-Encoding`, the uncompressed file's type, and repeats the CORS/CORP/nosniff headers.

Points to check when validating with `nginx -t` and a browser test (not done in this repository):

- Check that the server's `mime.types` maps `wasm` (`application/wasm`, in nginx 1.21+); load an original game from the games origin and confirm `.js` is served as JavaScript.
- Replace all `example.com` / `example-games.net` names and certificate paths.

TLS: obtain certificates for both hostnames, e.g. `certbot certonly --nginx -d www.example.com -d example.com` and `certbot certonly --nginx -d play.example-games.net`. HSTS (`max-age=31536000; includeSubDomains`) is sent by the app itself on HTTPS requests (`SecurityHeaders`); nginx does not add it to game files.

## PHP-FPM pool

`deploy/php/nebulo-fpm.conf`: pool `nebulo`, user `nebulo`, socket `/run/php/php8.4-fpm-nebulo.sock`, `pm = dynamic` with 24 max children, `request_terminate_timeout = 120s`, upload/post limits 210M, `memory_limit = 256M`, `expose_php = Off`, and `opcache.validate_timestamps = 0` (code changes need an FPM reload, which `deploy.sh` does). Tune `pm.max_children` to available RAM.

## Queue worker (supervisor)

`deploy/supervisor/nebulo-worker.conf` runs two `queue:work redis --tries=3 --max-time=3600 --timeout=1800` processes as `nebulo`, logging to `shared/storage/logs/worker.log`. `stopwaitsecs=1810` lets a long import finish on shutdown. See the `REDIS_QUEUE_RETRY_AFTER` note above.

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## Cron

`deploy/cron/nebulo` (as user `nebulo`):

```
* * * * *   cd /var/www/nebulo/current && php artisan schedule:run
15 2 * * *  /var/www/nebulo/current/deploy/scripts/backup.sh >> /var/www/nebulo/shared/storage/logs/backup.log
```

The schedule itself is in `routes/console.php` (see `docs/OPERATIONS.md`).

## deploy.sh

```bash
sudo -u nebulo /var/www/nebulo/current/deploy/scripts/deploy.sh <branch-or-tag>
```

(For the first deploy, run the script from a checkout since `current` does not exist yet.)

Steps:

1. `git clone --depth 1 --branch <ref>` of `NEBULO_REPO` (default is the project's GitHub repository over SSH; override with `NEBULO_REPO`). `--branch` accepts a branch or tag, **not a commit SHA**. The server needs a read-only deploy key.
2. Moves the repository's `platform/` folder into `releases/<timestamp>` and discards the rest.
3. Symlinks `.env`, `storage`, `public/game-files` to `shared/`.
4. `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build` (includes the Ruffle copy), removes `node_modules`.
5. `storage:link`, `php artisan down`, `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `event:cache`.
6. Atomically switches `current` to the new release, reloads PHP-FPM, `queue:restart`, `php artisan up`.
7. Requests `APP_URL/up`; on failure it prints a hint to run `rollback.sh` and exits non-zero (it does not roll back by itself).
8. Keeps the newest `NEBULO_KEEP_RELEASES` (default 5) releases.

Environment overrides: `NEBULO_BASE` (default `/var/www/nebulo`), `NEBULO_REPO`, `NEBULO_KEEP_RELEASES`.

Migrations run while the old release is still serving (in maintenance mode), so write backward-compatible migrations.

## rollback.sh

Points `current` at the most recent other release, reloads FPM, restarts the queue and brings the app up. **It does not reverse migrations.** If the failed release ran a destructive migration, restore the database from backup (see `docs/OPERATIONS.md`).

## backup.sh

Sources `shared/.env`, then:

- PostgreSQL: `pg_dump -Fc` to `db-<stamp>.dump`;
- MariaDB/MySQL: `mysqldump --single-transaction | gzip` to `db-<stamp>.sql.gz` (the password is passed in `MYSQL_PWD`, not on the command line);
- `tar` of `shared/storage/app/public` and `shared/game-files` to `files-<stamp>.tar.gz`;
- deletes local backups older than 14 days.

Default target is `/var/backups/nebulo` (`NEBULO_BACKUP_DIR`), which must be writable by `nebulo`. Copy backups off-site (rclone, restic or similar); the script does not. Because it uses `. .env`, the `.env` file must be valid shell syntax (quote values containing spaces).

## Continuous integration

`.github/workflows/platform-ci.yml` at the repository root runs on pushes and pull requests that touch `platform/**`:

- **test** job (matrix `db: [sqlite, pgsql]`, PostgreSQL 16 service): PHP 8.4, Node 22, `composer install`, `npm ci`, `npm run build`, key generation, Pint (`--test`, SQLite leg only), `php artisan test`.
- **browser** job (after `test`): installs Playwright Chromium, migrates and seeds SQLite with a test admin, starts `php -S ... scripts/dev-router.php`, runs `tests/browser/original-game-smoke.mjs`, `php artisan games:smoke --dry-run`, then `npx playwright test`. On failure it uploads screenshots, `test-results` and the server log.

CI does not deploy anything.

## Go-live checklist

1. Owner approval, domains (app + games origin) and credentials in hand.
2. DNS for both hostnames; TLS issued.
3. `nginx -t` passes; game files load from the games origin with correct MIME types and CORS (see the `types` note).
4. `shared/.env` complete (legal fields, `CONTACT_EMAIL`, mail, `GAMES_ORIGIN`, `REDIS_QUEUE_RETRY_AFTER`).
5. First deploy, then `php artisan db:seed --force` with `ADMIN_EMAIL`/`ADMIN_PASSWORD` set for that one command; log in and change the password.
6. Supervisor workers running; cron installed; first backup run and restore tested.
7. `php artisan games:smoke` passes on the production URL.
8. Review legal pages, brand availability (the brand name is provisional and its trademark/domain availability has not been checked), and keep ads disabled until approved.
