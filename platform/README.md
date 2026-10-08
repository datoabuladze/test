# Nebulo

A browser-games platform: a public site where people play free games instantly, and an admin panel where staff manage the catalog, licensing, content, design, ads and analytics. Built with Laravel 13, Blade, Tailwind CSS v4, Alpine.js (CSP build) and Vite.

"Nebulo" is a provisional name. Trademark and domain availability have not been checked.

## What is included

- **Public site** in English, Georgian, Turkish and Russian with language-specific URLs (`/en`, `/ka`, `/tr`, `/ru`) and hreflang tags: homepage with admin-configurable sections, 41 categories, tags, search with typo tolerance, game pages, leaderboards, profiles, blog and legal pages.
- **Universal player**: every game runs in a sandboxed iframe without `allow-same-origin`; supports original HTML5 games, Phaser/HTML5 packages, Unity WebGL builds, Flash through self-hosted Ruffle, and authorized third-party embeds.
- **21 original games** written for this project (code and art), all localized in four languages.
- **Accounts**: registration, email verification, favorites, history, ratings, XP levels, 12 achievements, leaderboards with anti-cheat (single-use sessions, plausibility limits, server-side replay verification for Merge Orbit).
- **Online multiplayer**: private two-player rooms for Tic-tac-toe and Four in a row, server-authoritative.
- **Admin panel** with six staff roles: games (packages, launch checks, previews, rights review, scheduled publishing, bulk actions), categories and homepage drag-and-drop ordering, imports from CSV, JSON or provider feeds with licensing validation, reports, users, pages, menus, design versions, ads, SEO redirects, translations, analytics and an audit log.
- **Operations**: scheduled stats and retention jobs, a real-browser launch test for every game (`php artisan games:smoke`), deploy/rollback/backup scripts, nginx and php-fpm configs, CI.

## Quick start

```bash
cd platform
composer install
npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='choose-a-strong-password' php artisan migrate --seed
npm run build            # also copies Ruffle to public/vendor/ruffle
php artisan storage:link
php -S 127.0.0.1:8000 -t public scripts/dev-router.php
```

Open http://127.0.0.1:8000 for the site and http://127.0.0.1:8000/admin for the admin panel. See [docs/INSTALLATION.md](docs/INSTALLATION.md) for details, including why the dev router is used instead of `php artisan serve`.

## Tests

```bash
php artisan test                    # PHPUnit: 204 tests
npx playwright test                 # end-to-end, needs the server above
node tests/browser/original-game-smoke.mjs
php artisan games:smoke --dry-run   # real-browser launch test of every catalog game
```

## Documentation

| Document | Contents |
|---|---|
| [ARCHITECTURE](docs/ARCHITECTURE.md) | How the system fits together |
| [INSTALLATION](docs/INSTALLATION.md) | Local setup |
| [DEPLOYMENT](docs/DEPLOYMENT.md) | Servers, nginx, workers, deploys and rollbacks |
| [ADMIN_GUIDE](docs/ADMIN_GUIDE.md) | Using the admin panel |
| [GAME_IMPORT_GUIDE](docs/GAME_IMPORT_GUIDE.md) | Importing licensed games and adding original ones |
| [GAME_LICENSES](docs/GAME_LICENSES.md) | Licenses of games and bundled software |
| [API_DOCUMENTATION](docs/API_DOCUMENTATION.md) | HTTP endpoints and the game frame protocol |
| [SECURITY](docs/SECURITY.md) | Security model and known limitations |
| [TESTING](docs/TESTING.md) | Test suites and how to run them |
| [OPERATIONS](docs/OPERATIONS.md) | Day-to-day running, takedowns, backups |
| [PROGRESS](docs/PROGRESS.md) | What is done, what is verified, what is outstanding |

## Content and licensing rules

Only games whose publication rights are verified can be public; the publish gate enforces it. No games are scraped or mirrored, Flash games are never downloaded automatically, there are no real-money or gambling mechanics, and the ads system never generates impressions or clicks itself.
