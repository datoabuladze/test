# Local installation

This guide sets up the platform for local development. All commands run inside `platform/` (the Laravel app is a subfolder of the repository; the repository root holds an unrelated older landing page).

## Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer (`composer.json` requires `^8.3`) | 8.4 is recommended and is what CI and `deploy/` use. This repository was developed on PHP 8.3. |
| PHP extensions | `gd` (with WebP support), `zip`, `intl`, `mbstring`, `pdo_sqlite` and/or `pdo_pgsql` / `pdo_mysql` | GD WebP is required: thumbnails and avatars are re-encoded to WebP. `zip` is required for game package uploads. |
| Composer | 2.x | |
| Node.js | 22 | Used for Vite, Tailwind, Ruffle copy, Playwright |
| Database | SQLite (default), PostgreSQL, or MariaDB/MySQL | CI runs the test suite on SQLite and PostgreSQL 16 |

Check GD WebP support:

```bash
php -r 'var_dump(function_exists("imagewebp"), gd_info()["WebP Support"] ?? false);'
```

## 1. Install dependencies

```bash
cd platform
composer install
npm ci
```

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Relevant settings in `.env` (see `.env.example` for all of them):

| Variable | Purpose |
|---|---|
| `APP_URL` | Must match the URL you browse (default `http://localhost:8000`; use `http://127.0.0.1:8000` if you start the server on that address). Frame CSP and smoke tests use it. |
| `DB_CONNECTION` | `sqlite` (default), `pgsql`, `mariadb` or `mysql` |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | All `database` by default; no Redis needed locally |
| `MAIL_MAILER` | `log` by default: verification and reset emails are written to `storage/logs/laravel.log` |
| `PLATFORM_BRAND` | Provisional brand name (default `Nebulo`) |
| `GAMES_ORIGIN` | Leave empty locally (games are served from the app origin) |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Used once by the seeder to create the first super admin |
| `GA4_MEASUREMENT_ID`, `ADSENSE_CLIENT` | Leave empty locally. The cookie banner only appears when one of them is set. |

## 3. Database

SQLite (simplest):

```bash
touch database/database.sqlite
```

PostgreSQL or MariaDB: create an empty database and user, then set in `.env`:

```dotenv
DB_CONNECTION=pgsql        # or mariadb / mysql
DB_HOST=127.0.0.1
DB_PORT=5432               # 3306 for MariaDB/MySQL
DB_DATABASE=nebulo
DB_USERNAME=nebulo
DB_PASSWORD=secret
```

## 4. Migrate and seed

```bash
ADMIN_EMAIL=admin@example.test ADMIN_PASSWORD='ChangeMe12345' php artisan migrate --seed
```

You can also put `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` instead of the command line. The seeders (`DatabaseSeeder`) run in this order:

1. `CategorySeeder` - category tree (`database/seeders/data/categories.php`)
2. `SiteSeeder` - "Originals" provider, default homepage sections, ad placements (all disabled), footer menus, initial theme version
3. `PageSeeder` - legal and info pages in en/ka/tr/ru (`database/seeders/data/pages/*.md`)
4. `AchievementSeeder`
5. `OriginalGamesSeeder` - registers every `public/games/originals/<key>/meta.json` as a published, rights-verified original game
6. `AdminUserSeeder` - creates a `super_admin` with a verified email from `ADMIN_EMAIL`. If `ADMIN_PASSWORD` is empty, a random 20-character password is printed once. If `ADMIN_EMAIL` is empty, no admin is created. An existing user with that email is left untouched.

Passwords must be at least 10 characters with letters and numbers (in production they are also checked against known breaches).

Seeders are idempotent enough to re-run (`php artisan db:seed`); `OriginalGamesSeeder` updates existing original games in place.

## 5. Build front-end assets

```bash
npm run build
```

This runs `vite build` and then `node scripts/copy-vendor.mjs`, which copies the self-hosted Ruffle Flash emulator from `node_modules/@ruffle-rs/ruffle` to `public/vendor/ruffle`. Flash games cannot run without that copy. To re-copy only Ruffle: `npm run vendor`.

For hot reload during development, run `npm run dev` in a second terminal (the local CSP allows the Vite dev server on `localhost:5173` / `127.0.0.1:5173` only when `APP_ENV=local`).

## 6. Storage link

```bash
php artisan storage:link
```

Uploaded thumbnails, avatars, ad images and branding files are stored on the `public` disk and served from `/storage`.

## 7. Run the app

Use PHP's built-in server with the included router:

```bash
php -S 127.0.0.1:8000 -t public scripts/dev-router.php
```

Then open `http://127.0.0.1:8000` (it redirects to `/{locale}/`). The admin panel is at `/admin`.

### Why the dev router

Games run in iframes sandboxed **without** `allow-same-origin`, so the game document has an opaque (`null`) origin. Any request it makes for its own assets (scripts loaded as modules, JSON, WebAssembly, audio, Ruffle's `.wasm` chunks, Unity build files) is cross-origin and needs `Access-Control-Allow-Origin`. `scripts/dev-router.php` mirrors the production nginx rules for `/games/`, `/game-files/` and `/vendor/ruffle/`:

- sends `Access-Control-Allow-Origin: *`, `Cross-Origin-Resource-Policy: cross-origin`, `X-Content-Type-Options: nosniff` and correct MIME types (including `application/wasm`);
- never executes `.php` files under those paths.

`php artisan serve` works for the site, admin panel and the original games (they use classic scripts only), but Ruffle (Flash) games and other wasm-loading games fail to load under it because those headers are missing. The Playwright tests and the CI browser job use the dev router.

## 8. Queue worker

```bash
php artisan queue:work
```

Most work runs synchronously, but these go to the queue:

- import previews for uploaded files larger than 2 MB and all provider-feed previews;
- import runs of more than 300 rows;
- queued mail, if you configure it.

Without a worker those batches stay in `queued`/`running`.

## 9. Scheduler

Run the scheduler in the foreground while developing:

```bash
php artisan schedule:work
```

Or run individual commands when needed:

```bash
php artisan stats:aggregate --days=2   # recompute stats, popularity and trending scores
php artisan platform:prune             # retention cleanup
php artisan games:smoke --untested     # real-browser launch test (needs Playwright + Chromium)
```

`games:smoke` and the browser tests need Chromium. Install it with `npx playwright install chromium` and point `CHROMIUM_PATH` at the binary if it is not at `/opt/pw-browsers/chromium` (see `docs/TESTING.md`).

## Troubleshooting

| Symptom | Fix |
|---|---|
| Thumbnail or avatar upload fails with "could not be read" / unsupported format | GD missing or built without WebP |
| Package upload fails immediately | `zip` extension missing, or `upload_max_filesize`/`post_max_size` below the package size (`MAX_GAME_PACKAGE_KB`, default 200 MB) |
| Flash game shows "Flash emulator failed to download" | Run `npm run build` (or `npm run vendor`) and use the dev router |
| Game player times out after 30 s | An original, Unity or Ruffle game did not send `nebulo:ready`; see `docs/API_DOCUMENTATION.md` |
| Catalog changes do not appear | `php artisan cache:clear` (catalog queries are cached 5 minutes, versioned by `catalog.version`) |
| Links point to the wrong host | `APP_URL` does not match the address you are using |
