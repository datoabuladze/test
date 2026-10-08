# Progress

Status as of 2026-10-08. Everything marked **verified** was run and observed in the development environment described at the end. Nothing has been deployed to production.

## Verified numbers

| Item | Value | How it was checked |
|---|---|---|
| Original games | 21 | Every one passed `node tests/browser/original-game-smoke.mjs` on desktop and mobile viewports (42 of 42 runs) with no console errors |
| Catalog launch test | 21 of 21 passed | `php artisan games:smoke`: each game loaded through its real `/frame/{slug}` URL in the production sandbox inside Chromium |
| Public games in the seeded catalog | 21 | All originals; no third-party games are included (see below) |
| Languages | 4 (en, ka, tr, ru) | Pages return 200 in all four; UI strings: 298 keys per language; legal and info pages: 9 per language |
| Categories | 41 | Seeded, each with names in four languages |
| Achievements | 12 | Seeded |
| Homepage sections | 24, reorderable | Admin drag-and-drop reorder endpoint tested |
| Routes | 137 (76 admin, 15 API) | `php artisan route:list` |
| Database tables | 39 | Counted in PostgreSQL 16 after migrations |
| PHPUnit | 210 tests, 1546 assertions, all passing | `php artisan test` on SQLite and on PostgreSQL 16 |
| Playwright end-to-end | 19 tests, all passing | `npx playwright test` (desktop and Pixel 7 projects) against the local server |
| Admin pages | All GET pages return 200 as super admin | curl probe of every admin index/create/edit page |
| Ruffle (Flash) pipeline | Works end to end | A blank SWF made for testing was uploaded through the admin package form, loaded by self-hosted Ruffle in the sandboxed frame, and reported ready (the test game was deleted afterwards) |
| Local page metrics | LCP 216–360 ms, CLS 0 | Chromium, local PHP built-in server, home/game/category/search pages, desktop and mobile emulation. These are not production or Lighthouse figures |

## Done

- Public site: homepage with configurable sections, game pages with the universal player, categories, tags, search with typo correction and live suggestions, leaderboards, profiles, blog, legal pages, sitemaps per locale, hreflang, robots, JSON-LD.
- Player: deferred loading, sandboxed iframe without `allow-same-origin`, fullscreen with an iOS fallback, rotate hint, mute, restart, load timeout and error screen, launch analytics.
- Engines: original SDK games, HTML5/Phaser packages, Unity WebGL wrapper, Ruffle wrapper, authorized iframe embeds with a provider host allow-list.
- Accounts: registration, login, password reset without account enumeration, email verification, favorites, history, ratings, XP and levels, achievements, avatar upload, account deletion, suspension.
- Anti-cheat: single-use score sessions (a rejected score also burns its session), plausibility limits, deterministic replay verification for Merge Orbit.
- Multiplayer: private rooms for Tic-tac-toe and Four in a row, server-authoritative, over HTTP polling.
- Admin: dashboard, games (bulk actions, package upload, launch check, preview, rights review, scheduled publishing, trash), categories and homepage drag-and-drop, tags, providers, imports (CSV, JSON, provider feed) with preview and licensing validation, reports, users and roles, pages and blog, menus, design versions, branding, ads, SEO redirects, settings, UI translation editor, analytics, audit log. Six staff roles.
- Operations: hourly stats aggregation and ranking, daily retention pruning matching the privacy policy, daily browser launch test, cookie consent that gates GA4 and AdSense, deploy/rollback/backup scripts, nginx/php-fpm/supervisor/cron configs, CI workflow, documentation.

## Not done, or not verified

- **Third-party games.** None are included. Reaching a large catalog needs provider agreements or games with licenses that allow redistribution; the import pipeline is ready, but no provider has been approved, and no games were scraped or invented to fill the gap.
- **GameDistribution adapter.** The field mapping follows the provider's export format but has not been tested against a live feed.
- **Unity WebGL.** The wrapper and package detection exist; no Unity build was available to test them.
- **Phaser.** Detected on upload and served like HTML5; no Phaser game was tested.
- **CI on GitHub.** The workflow is in the repo, but every job on this repository stopped within about two seconds without starting a runner and without logs, because GitHub has locked the account over a billing issue (the check annotation says so). The same commands pass locally.
- **Production deployment.** Not performed. It needs the owner's approval, a domain, TLS, a server and credentials. The nginx config was not validated with `nginx -t` here.
- **PHP 8.4.** Developed and tested on PHP 8.3.6; the deploy configs assume 8.4.
- **Redis.** The app runs with Redis for cache and sessions (catalog caching checked). Running the PHPUnit suite with Redis as the cache fails 9 tests because rate-limiter state persists between tests; the suite is meant to run with the array cache from `phpunit.xml`.
- **Lighthouse.** Not run; only the local metrics above were measured.
- **AdSense.** Code paths exist but were not exercised with a real client id. Serving AdSense to EEA/UK visitors needs a Google-certified consent platform; the built-in banner is not one.
- **Brand.** "Nebulo" trademark and domain availability are unchecked.
- **Smaller known gaps** are listed in [SECURITY.md](SECURITY.md#known-limitations), among them: email verification is not required for any feature, registration reveals whether an email is taken, and staff with import rights can point feed and thumbnail fetches at internal HTTPS hosts.
- **Admin panel language.** The admin panel is English only; the public site is fully localized.

## Development environment used for verification

Ubuntu container, PHP 8.3.6, SQLite and PostgreSQL 16, Redis 7, Node 22, Chromium (Playwright 1.64), PHP built-in server with `scripts/dev-router.php`.
