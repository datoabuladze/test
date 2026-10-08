# Testing

The project has four layers of automated checks: PHPUnit (unit and feature tests), Playwright end-to-end tests, two browser harnesses for games, and Pint for code style. CI runs all of them (see the last section). This document lists what exists; it does not claim current pass counts. Run the suites to see their status.

All commands run inside `platform/`.

## PHPUnit

```bash
php artisan test                         # everything
php artisan test --testsuite=Unit
php artisan test --filter=ScoreSubmissionTest
composer test                            # clears config cache, then runs php artisan test
```

`phpunit.xml` forces `APP_ENV=testing`, SQLite in memory, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array` and `BCRYPT_ROUNDS=4`. Tests use `RefreshDatabase`. To run against PostgreSQL (as CI does), set the variables in the environment:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=nebulo_test DB_USERNAME=nebulo DB_PASSWORD=nebulo php artisan test
```

The `<env>` entries in `phpunit.xml` are not marked `force="true"`, so variables already set in the shell take precedence. This is how the CI PostgreSQL leg works.

### Test files (as of 2026-10-08)

`tests/Feature`:

| File | Covers |
|---|---|
| `AuthTest.php` | Registration (incl. closed registration), login/logout, wrong password, login throttling, suspended users, password reset without enumeration, account area auth, guest-only redirects |
| `CatalogVisibilityTest.php` | `Game::scopePublic()` rules (status, rights, schedule, failed launch, Ruffle compatibility, soft delete); public/hidden game pages; category and games listings |
| `EngagementApiTest.php` | Ratings, favorites, reports (validation, hashed IP, throttling, hidden games), play start + XP |
| `GamePublisherTest.php` | Every publish blocker, publish/schedule/unpublish, provider host allow-list matching |
| `GamificationTest.php` | Idempotent XP ledger, daily play XP, level recomputation, achievement unlocks |
| `LocalizationSeoTest.php` | Home in every locale, unknown locale 404, root redirect, hreflang alternates, sitemaps, robots.txt |
| `MultiplayerRoomTest.php` | Room creation/join, Tic-tac-toe and Connect Four moves and wins, spectators, forged seat cookies, expiry, room page, `RoomManager` flow |
| `RedirectTest.php` | Admin redirects (only on 404, status codes, localized paths, admin creation) |
| `ScoreSubmissionTest.php` | Score sessions, single use, ownership, expiry, plausibility limits, token not consumed on rejection, Merge Orbit replay verified / inflated / tampered / no evidence |
| `SearchTest.php` | Normalisation, multilingual titles, tags and categories, typo tolerance, ranking, hidden games, suggest endpoint, query logging, LIKE wildcard escaping |
| `SecurityHeadersTest.php` | Nonce CSP and frame protection, per-request nonce, JSON responses without HTML CSP, game frame access (public, signed preview, staff, signature scoping), Ruffle wrapper |

`tests/Feature/Admin`:

| File | Covers |
|---|---|
| `AdminAccessTest.php` | Guest redirect, players 403, suspended staff, per-role section access, moderator limits, role changes, super-admin protection, game-manager vs content-editor publishing, staff viewing hidden games |
| `AdminGameFlowTest.php` | Admin pages render, draft creation, validation, rights review rules, rights reset on licence edits, publish/schedule/unpublish endpoints, bulk publish and permissions, trash/restore/force delete, bulk trash |
| `GamePackageUploadTest.php` | ZIP install, nested folder + Phaser detection, traversal/absolute paths, PHP files, missing index, garbage files, disallowed extensions, fake and valid SWF, permission |
| `ImportTest.php` | CSV preview without touching the catalog, licence defaults, in-file duplicates, drafts with unverified rights, review before publish, selected rows, JSON import, invalid JSON, run endpoint, permissions |

`tests/Unit`:

| File | Covers |
|---|---|
| `LevelCurveTest.php` | XP-to-level curve |
| `MergeOrbitVerifierTest.php` | `Mulberry32` determinism and parity with reference JavaScript output; replay determinism; invalid evidence; no-op moves invalidate a run |
| `Multiplayer/TicTacToeTest.php` | Rules: turns, occupied/out-of-range cells, wins, draws, game over |
| `Multiplayer/ConnectFourTest.php` | Rules: gravity, stacking, turns, illegal/full columns, horizontal/vertical/diagonal wins |

Factories exist for `User`, `Game`, `Category`, `Provider` and `Tag` (`database/factories/`). `tests/TestCase.php` provides helpers such as `userWithRole()`.

## Playwright end-to-end tests

Config: `playwright.config.mjs`. Tests: `tests/e2e/*.spec.mjs`.

They run against a **running server with a seeded database**; they do not start one.

```bash
touch database/database.sqlite
ADMIN_EMAIL=admin@nebulo.test ADMIN_PASSWORD=AdminPass12345 php artisan migrate:fresh --seed
npm run build
php -S 127.0.0.1:8000 -t public scripts/dev-router.php &
npx playwright install chromium        # once
CHROMIUM_PATH="$(node -e "console.log(require('@playwright/test').chromium.executablePath())")" npx playwright test
```

| Variable | Default | Purpose |
|---|---|---|
| `E2E_BASE_URL` | `http://127.0.0.1:8000` | Server under test |
| `CHROMIUM_PATH` | `/opt/pw-browsers/chromium` | Chromium executable |
| `E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD` | `admin@nebulo.test` / `AdminPass12345` | Admin account created by the seeder |

Projects: `desktop` (Desktop Chrome, all tests) and `mobile` (Pixel 7, only tests tagged `@mobile`). Two workers, 45 s timeout, no retries, traces kept on failure in `test-results/`.

| Spec | Scenarios |
|---|---|
| `public.spec.mjs` | Home in each locale without console errors (`@mobile`); live search suggestions and results; typo-tolerant search; a game loads in the sandboxed player after pressing Play (`@mobile`); category, legal page, sitemap, robots; localized 404 |
| `account.spec.mjs` | Register, favorite a game, see it in favorites, log out; wrong-password login without account disclosure |
| `admin.spec.mjs` | Admin login, game filtering, game preview; guests redirected to login |
| `multiplayer.spec.mjs` | Two browser contexts join a Tic-tac-toe room and play to a win |

`helpers.mjs` collects page errors and console errors (ignoring favicon requests).

## Original game smoke harness

```bash
CHROMIUM_PATH=... node tests/browser/original-game-smoke.mjs            # all originals
CHROMIUM_PATH=... node tests/browser/original-game-smoke.mjs merge-orbit  # selected keys
```

Serves `public/` on a random local port (no Laravel needed), embeds each `public/games/originals/<key>/index.html` in an iframe with `sandbox="allow-scripts"`, and for desktop (1280x800) and mobile touch (390x844) viewports: waits for `nebulo:ready`, clicks the Play action, sends arrow/space/enter keys and clicks or taps on the stage, and fails on any page or console error. Screenshots go to `storage/app/smoke-<key>-<viewport>.png`. Exit code 1 if any game fails.

## Catalog launch test

```bash
php artisan games:smoke                    # all games with files or an embed URL
php artisan games:smoke --untested         # never tested, untested, or not checked for 7 days
php artisan games:smoke --game=merge-orbit --game=neon-snake
php artisan games:smoke --engine=ruffle
php artisan games:smoke --dry-run          # print results, save nothing
```

The command writes a JSON payload (app URL, sandbox and `allow` attributes, each game's frame URL; signed preview URLs for non-public games) and runs `node tests/browser/catalog-smoke.mjs <file>`. The script opens a page on the app origin, loads each frame URL in an iframe with the production sandbox, waits up to 20 s for `nebulo:ready`/`nebulo:error` for original, Unity and Ruffle games (or the `load` event for uploaded HTML5/Phaser packages and `iframe` embeds), then watches for runtime errors for 2.5 s. Results are printed as a table. Without `--dry-run`, each game's `launch_status` becomes `ok` or `failed` with a message; **a failed game is hidden from the public catalog** until fixed. The command needs Node, Playwright, Chromium (`CHROMIUM_PATH`) and the app reachable at `APP_URL`. It exits non-zero if any game failed.


## Code style

```bash
vendor/bin/pint            # fix
vendor/bin/pint --test     # check only (CI)
```

Pint uses Laravel's default preset (no `pint.json`).

## Continuous integration

`.github/workflows/platform-ci.yml` (repository root), on pushes and pull requests touching `platform/**`:

| Job | Matrix | Steps |
|---|---|---|
| `test` | `db: [sqlite, pgsql]` (PostgreSQL 16 service) | PHP 8.4 with gd, zip, intl, pdo_sqlite, pdo_pgsql, mbstring; Node 22; `composer install`; `npm ci`; `npm run build`; `.env` from example + key; Pint `--test` (sqlite leg only); `php artisan test` with `DB_CONNECTION` from the matrix |
| `browser` | - (needs `test`) | Installs Playwright Chromium; migrates and seeds SQLite with `admin@nebulo.test`; starts the dev-router server; runs the original game smoke harness, `games:smoke --dry-run`, then `npx playwright test`; uploads screenshots, `test-results` and the server log on failure |

## Manual checks not covered by automation

- Real Flash games under Ruffle (the fixture `tests/fixtures/blank.swf` only checks installation and the wrapper).
- Unity WebGL builds.
- Third-party embeds from a real provider and the GameDistribution feed mapping.
- The nginx configuration and the separate games origin.
- Email delivery (tests use the array mailer).
