# Architecture

Nebulo (provisional brand, set by `PLATFORM_BRAND`) is a Laravel 13 browser-games platform. This document describes how the code in `platform/` is organised and how a request moves through it. It describes the code as it exists; anything configured but not verified is marked as such.

## Repository layout

The Laravel application lives in the `platform/` subfolder of the repository. The repository root holds an unrelated older static landing page (`index.html`, `styles.css`, `stili.css`, `media.css`, `img/`) and the CI workflow `.github/workflows/platform-ci.yml`. Nothing in `platform/` depends on the root files.

| Path (under `platform/`) | Contents |
|---|---|
| `app/Enums` | `Role`, `GameEngine`, `GameStatus`, `RightsStatus`, `FlashCompatibility`, `HomeSectionType` |
| `app/Http/Controllers` | Public site, `Account/`, `Auth/`, `Api/` (JSON), `Admin/`, `GameFrameController` |
| `app/Http/Middleware` | `SetLocale`, `SetLocaleFromSession`, `SecurityHeaders`, `EnsurePermission`, `HandleRedirects`, `TouchLastActive` |
| `app/Models` | Eloquent models; `Concerns/HasTranslations` for JSON translatable columns |
| `app/Services` | Domain logic: `GamePublisher`, `GameCatalog`, `SearchService`, `ScoreService`, `Gamification`, `GamePackageInstaller`, `ImageProcessor`, `LaunchChecker`, `EmbedValidator`, `Ads`, `Audit`, `Visitor`, `Import/`, `Multiplayer/`, `Verifiers/` |
| `app/Support` | `Seo`, `Translatable`, `ContentTokens` |
| `app/Console/Commands` | `stats:aggregate`, `platform:prune`, `games:smoke` |
| `app/Jobs` | `PreviewImportBatch`, `RunImportBatch` |
| `config/platform.php` | Platform settings: locales, games origin, iframe sandbox, upload limits, score defaults, privacy salt, analytics/ads ids, import adapters |
| `database/` | Migrations (3 platform migrations + Laravel defaults), seeders, factories |
| `deploy/` | nginx, php-fpm, supervisor, cron, deploy/rollback/backup scripts, production env example |
| `public/games/originals/` | First-party games and the shared SDK (`_sdk/`) |
| `public/game-files/` | Uploaded game packages (created at runtime, git-ignored) |
| `public/vendor/ruffle/` | Self-hosted Ruffle, copied from npm at build time |
| `resources/js` | `app.js`, `player.js`, `room.js`, `admin.js`, `lib/` |
| `routes/web.php` | All HTTP routes (there is no `routes/api.php`) |
| `routes/console.php` | Schedule |
| `scripts/` | `copy-vendor.mjs`, `dev-router.php` |
| `tests/` | PHPUnit (`Feature/`, `Unit/`), Playwright (`e2e/`), browser harnesses (`browser/`) |

## Overview diagram

```
                        Browser (app origin, APP_URL)
   +---------------------------------------------------------------------+
   |  Page /{locale}/game/{slug}    Alpine (CSP build) + player.js        |
   |   |                                                                 |
   |   |  postMessage (nebulo:*)    <iframe sandbox="allow-scripts ...">  |
   |   |<-------------------------->  (no allow-same-origin => opaque    |
   |   |                               origin)                           |
   +---|-----------------------------------------|-----------------------+
       | fetch /api/* (session cookie, CSRF)     | GET {GAMES_ORIGIN or APP_URL}/frame/{slug}
       v                                         v
   +------------------------------+     +-------------------------------+
   | Laravel app (php-fpm)        |     | GameFrameController           |
   |  web middleware:             |     |  original/html5/phaser ->     |
   |   SecurityHeaders (CSP nonce)|     |    302 to static entry file   |
   |   TouchLastActive            |     |  ruffle -> wrapper page       |
   |  route middleware:           |     |  unity  -> wrapper page       |
   |   locale / locale.session    |     |  iframe -> 404 (player embeds |
   |   permission:<perm>          |     |    the authorized URL itself) |
   |   throttle:<limiter>         |     +---------------|---------------+
   |  global: HandleRedirects     |                     v
   +------|-----------------------+     Static files: /games, /game-files,
          |                             /vendor/ruffle (nginx, CORS *)
          v
   DB (sqlite / pgsql / mariadb)   Cache + queue + session (database by default,
                                   redis in deploy/env.production.example)
```

## Request flow

1. `public/index.php` boots the app configured in `bootstrap/app.php`.
2. Global middleware: Laravel defaults plus `HandleRedirects` (appended globally so it also sees 404s for paths that match no route). Trusted proxies come from `TRUSTED_PROXIES` (default `127.0.0.1`).
3. `web` group: Laravel defaults plus `SecurityHeaders` (security headers and nonce CSP) and `TouchLastActive` (logs out suspended users; updates `last_active_at` at most every 10 minutes).
4. Route middleware aliases: `locale` (`SetLocale`), `locale.session` (`SetLocaleFromSession`), `permission` (`EnsurePermission`).
5. JSON errors: requests to `api/*`, or those that expect JSON, get JSON exception responses.
6. The health route `/up` is registered by `withRouting(health: '/up')`.

## Locale-prefixed routing

- Supported locales are defined in `config/platform.php` (`locales`): `en`, `ka`, `tr`, `ru`. The default is `en`.
- All public pages live under `/{locale}/...` (`Route::prefix('{locale}')` with a regex constraint built from the locale keys). `SetLocale` sets the app locale, sets `URL::defaults(['locale' => ...])` so `route()` calls do not need the parameter, removes the `locale` parameter so controllers receive only their own parameters, and stores the locale in the session.
- `/` redirects (302) to `/{preferred}/` where `SetLocale::preferred()` picks, in order: session locale, the user's saved locale, then `Accept-Language`, then the default.
- Non-prefixed routes (`/logout`, `/email/verify/...`, `/email/verification-notification`, all `/api/*`) use `locale.session` (`SetLocaleFromSession`), which applies the preferred locale without a URL prefix.
- UI strings use `__('English source')`; translations ship in `lang/{ka,tr,ru}.json`; edits from Admin > Translations are stored in `storage/app/lang-overrides/` and applied on top by `App\Support\OverridableFileLoader`. `lang/en/*.php` holds the framework validation/auth strings.

## Models and translatable columns

Translatable content is stored as JSON objects keyed by locale (for example `{"en": "Merge Orbit", "ka": "..."}`) using the `HasTranslations` trait:

- A model lists its columns in `protected array $translatable`; the trait casts them to `array`.
- `$model->tr('title')` returns the current locale's value, falling back to the default locale, then to the first non-empty value.
- `setTranslation()`, `hasTranslation()`, `getTranslatableAttributes()` are also provided.
- Admin forms post `field[en]`, `field[ka]`, ...; `App\Support\Translatable::rules()` builds validation (English may be required) and `Translatable::clean()` drops empty or unknown locales.

| Model | Translatable columns |
|---|---|
| `Game` | title, short_description, description, instructions, controls, seo_title, seo_description |
| `Category` | name, description, seo_title, seo_description |
| `Tag` | name |
| `Page` | title, excerpt, body, seo_title, seo_description |
| `HomepageSection` | title (plus `config.body` for SEO text sections) |
| `MenuItem` | label |
| `Achievement` | name, description |

Other key models: `Provider` (import source, allow-listed embed hosts, licence defaults in `settings`), `GamePlay`, `GameStatDaily`, `Rating`, `Score`, `ScoreSession`, `XpEvent`, `UserAchievement`, `GameReport`, `GameRoom`, `SearchQuery`, `ImportBatch`/`ImportItem`, `AdPlacement`/`AdCampaign`/`AdStatDaily`, `ThemeVersion`, `Setting` (key/JSON value, cached forever under `settings.all`), `Redirect`, `AuditLog`. `Game` uses soft deletes. `Game.search_text` is a denormalised lowercase string (titles in all locales, short descriptions, developer, slug words, tags, categories, provider name) rebuilt on save.

## Catalog visibility gate

`Game::scopePublic()` is the single definition of "visible to visitors". A game is public only when all of the following hold:

- `status = published`
- `rights_status = verified`
- `published_at` is set and not in the future (scheduled releases appear automatically)
- `launch_status != failed`
- for `engine = ruffle`: `flash_compatibility` is `compatible` or `partial`
- not soft-deleted

Every public listing, search, sitemap, rating, favorite, report, score and frame endpoint goes through `public()` / `isPublic()`. Staff with `games.manage` can open non-public game pages (marked `noindex`) and frames.

`App\Services\GamePublisher` is the only code path that sets a game to `published`. `blockers()` returns the reasons a game cannot be published (rights not verified, missing licence type, missing source URL for non-originals, missing hosting method, missing files or embed URL, embed not authorized or not HTTPS on an allow-listed host, Flash compatibility not playable, last launch check failed, thumbnail rights unconfirmed for non-originals, missing English title). `publish()` throws `PublishException` if any exist; it accepts an optional future time for scheduling. Both `publish()` and `unpublish()` flush the catalog cache and write an audit entry.

Exceptions to note: `OriginalGamesSeeder` writes `status = published` directly for first-party games (rights verified at source), and `LaunchChecker`/`games:smoke` change `launch_status`, which can hide or re-show an already published game.

## Caching

- Default store is `database` (`CACHE_STORE`); production example uses Redis.
- `GameCatalog` caches listing queries for 300 s under keys `catalog.v{N}.<query>`. `N` is the integer in cache key `catalog.version`. `GameCatalog::flush()` increments it (invalidating every catalog key at once) and forgets `home.sections`. It is called by publish/unpublish, game edits, category/provider changes, imports, launch checks and `stats:aggregate`.
- Other keys: `categories.tree`, `menus.all` (600 s), `home.sections` (600 s), `theme.active`, `settings.all`, `redirects.map` (forever, cleared on change), `ads.placement.<key>` (300 s), `search.categories` (600 s), `search.trending.<n>` (900 s), `search.vocabulary.v{N}` (3600 s), `sitemap.*` (3600 s).
- `config/cache.php` sets `serializable_classes` to an allow-list (Eloquent `Collection`, support `Collection`, `Pivot`, `Game`, `Category`, `Tag`, `MenuItem`, `HomepageSection`, `AdPlacement`, `Provider`). Any other class read from the cache unserializes as an incomplete object. If you cache a new model type, add it to this list.

## Sandboxed player and postMessage protocol

`resources/js/player.js` registers the `gamePlayer` Alpine component on game pages (loaded only there). Nothing loads until the visitor presses Play. It then:

1. Creates an `<iframe>` with `sandbox` = `config('platform.iframe_sandbox')` (`allow-scripts allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-forms`, no `allow-same-origin`) and `allow` = `fullscreen; gamepad; autoplay; accelerometer; gyroscope`. The frame document therefore has an opaque origin and cannot read the page, its cookies or storage.
2. Calls `POST /api/games/{slug}/plays` (analytics; failures never block play) and, for logged-in users on games with scores, `POST /api/games/{slug}/score-session`.
3. Waits up to 30 s for the game to be ready. For engines `original`, `unity`, `ruffle` (frame documents the platform controls) the `nebulo:ready` message is required; for uploaded `html5`/`phaser` packages and `iframe` embeds, which are third-party code, the frame `load` event counts as ready.

Messages (validated by `event.source === iframe.contentWindow`):

| Direction | Message |
|---|---|
| frame -> page | `{type:'nebulo:ready'}`, `{type:'nebulo:score', score}`, `{type:'nebulo:gameover', score, durationMs, evidence}`, `{type:'nebulo:error', message}` |
| page -> frame | `{type:'nebulo:mute', muted}`, `{type:'nebulo:pause'}`, `{type:'nebulo:resume'}`, `{type:'nebulo:session', seed}` |

The page posts with target `'*'` because the frame origin is opaque; it only sends UI commands. The shared SDK for first-party games is `public/games/originals/_sdk/sdk.js` (`window.NebuloGame`); see `public/games/originals/_sdk/README.md` and `docs/API_DOCUMENTATION.md`.

## Engines and GameFrameController

`App\Enums\GameEngine`:

| Engine | Source | Frame served by `/frame/{game}` |
|---|---|---|
| `original` | `public/games/originals/<key>/index.html` | 302 redirect to the entry file, with `?lang=<locale>` |
| `html5` | Uploaded ZIP in `public/game-files/<id>-<random>/` | 302 redirect to the entry file |
| `phaser` | Uploaded ZIP (detected by "phaser" in `index.html`) | 302 redirect to the entry file |
| `unity` | Uploaded ZIP with `Build/*.loader.js` | `frames/unity` wrapper page |
| `ruffle` | Uploaded SWF, played by self-hosted Ruffle | `frames/ruffle` wrapper page (networking and script access disabled) |
| `iframe` | Authorized third-party embed URL | 404; the player puts the embed URL directly into the sandboxed iframe |

`GameFrameController::show` serves public games, staff with `games.manage`, or requests with a valid relative signature (`GameController::frameUrl($game, preview: true)` makes a 30-minute signed URL; used by admin preview and `games:smoke` for non-public games, because the session cookie is not sent to a separate games origin). Frame responses get their own CSP (`frame-ancestors 'self' APP_URL`, `wasm-unsafe-eval`, `object-src 'none'`, `base-uri 'none'`), no `X-Frame-Options`, `Cross-Origin-Resource-Policy: cross-origin` and `Cache-Control: public, max-age=300`.

## Games origin

`GAMES_ORIGIN` (`config('platform.games_origin')`) is an optional separate registrable domain that serves only static game files plus `/frame/*`. When set, frame URLs and asset URLs use it, so game code never shares the app's origin even if the sandbox were weakened. When empty, the app origin is used and isolation relies on the sandbox alone. `deploy/nginx/nebulo.conf` contains a second server block for this origin. Static game paths need `Access-Control-Allow-Origin: *` because opaque-origin frames make cross-origin requests for their assets.

## Scores and anti-cheat

`App\Services\ScoreService`, used by `Api\ScoreController` (logged-in users only):

1. **Session token.** `score-session` issues a single-use 48-character token bound to user, game, a random seed and the server start time (`score_sessions`). Sessions older than 180 minutes are rejected and pruned.
2. **Plausibility.** The submitted `duration_ms` may not exceed real elapsed server time + 5 s; the score must be `>= 0`, `<= engine_config.max_score`, and `<= max(50, max_points_per_second * duration_s)` (default rate 1000/s, overridable per game in `engine_config`). A failed check returns 422 and does not consume the token (the whole submission runs in a transaction).
3. **Replay verification.** If `score_mode = verified` and `engine_config.verifier` names a `ScoreVerifier`, the server replays the evidence from the session seed. Only an exact match sets `is_verified = true` (`verification = replay`); otherwise the score is stored as casual (`verification = plausibility`).

The only verifier is `App\Services\Verifiers\MergeOrbitVerifier`: it ports Merge Orbit's 4x4 merge logic and the SDK's `mulberry32` PRNG (`Verifiers\Mulberry32`, bit-exact with the JS) and replays a move string `^[LRUD]+$` (max 100,000 moves; any move that does not change the board invalidates the run). Leaderboards (`LeaderboardController`) default to the verified board for verified games and the casual board otherwise; they list only public profiles of non-suspended users.

## Gamification (XP ledger)

`App\Services\Gamification`:

- All XP goes through `xp_events` with a unique `(user_id, reason, ref)` and `insertOrIgnore`, so awards are idempotent. `users.xp` is recomputed as the ledger sum; `level` uses `xpForLevel(n) = round(100 * (n-1)^1.5)`.
- On play start (logged in): 20 XP once per day (`daily`), 5 XP per distinct game per day (`play`).
- `evaluate()` checks active achievements (`metric`: plays, distinct_games, favorites, ratings, score, streak, level; `period`: lifetime, daily, weekly, monthly; optional `game_id`) and records unlocks in `user_achievements` keyed by period, awarding `xp_reward`. It runs after plays, favorites, ratings and score submissions.

## Multiplayer rooms

Private two-player rooms for Tic-tac-toe and Connect Four (`App\Services\Multiplayer`):

- **Transport: HTTP polling.** `resources/js/room.js` polls `GET /api/rooms/{code}` every 1.2 s. There are no WebSockets.
- **Server-authoritative.** Clients send intents (`cell` or `col`); `Rules::apply()` validates turn, bounds and occupancy and throws `InvalidMove`. Actions run inside a transaction with `lockForUpdate()` on the room row; a `version` counter increments on each change.
- **Seats.** Creating or joining sets an httpOnly cookie `room_<CODE>` holding a 48-character seat token; `hash_equals` maps it to seat 0 (host), 1 (guest) or spectator. Reloading the page reconnects automatically.
- Room codes are 6 uppercase characters avoiding `O 0 I 1`. Rooms expire 120 minutes after last activity (410 Gone). Presence = seen within 20 s. First player alternates per round; `rematch` starts a new round.

## Search

`App\Services\SearchService` is database-agnostic (no full-text index):

1. Normalise the query (lowercase, strip punctuation, max 80 chars) and split into tokens.
2. Candidates: public games where every token appears in `search_text` (`LIKE` with escaped wildcards), max 400, plus filters (category incl. descendants, `device=mobile`, multiplayer, engine, difficulty, provider).
3. If fewer than 3 candidates, correct tokens against a cached vocabulary of `search_text` words using multibyte Levenshtein (distance 1 for tokens of 3-5 chars, 2 for longer) and retry.
4. Rank in PHP: exact title 1000, prefix 600, substring 400, word-prefix hits 100 each, plus token hits, log-scaled plays, featured bonus.

`/api/search/suggest` returns up to 8 games and 4 matching categories. Search pages are `noindex`; first-page queries are logged to `search_queries` (normalised text, locale, result count, no user id).

## Analytics and privacy

- `game_plays` rows store `visitor_hash`, device class, locale, country and referrer host. `Visitor::hash()` is `sha256(VISITOR_HASH_SALT | date | ip | user-agent)`: it changes daily and is not reversible, and no raw visitor IP is stored. Country is taken only from a CDN header (`CF-IPCountry`), never from IP lookup.
- `stats:aggregate` rolls plays into `game_stats_daily` and recomputes counters, `popularity_score` and `trending_score`.
- Admin > Analytics is computed from these tables; "visitor-days" is the sum of daily unique hashes.
- GA4 loads only if `GA4_MEASUREMENT_ID` is set and the visitor chose "Accept all" in the cookie banner.

## Ads

- Placements (`home_top`, `home_mid`, `category_top`, `game_below`, `sidebar`) are seeded disabled. No placement exists inside the player.
- Campaign types: `direct` (uploaded image + HTTPS target), `sponsored_game` (links to a game), `adsense` (slot id; rendered only when `ADSENSE_CLIENT` is set, and the AdSense script loads only after "Accept all").
- `Ads::forPlacement()` picks a running campaign by priority, skips it if it would render nothing (AdSense without a client id, missing image or game), and records a served impression in `ad_stats_daily` for house and sponsored campaigns; clicks go through `/ad/{campaign}/click`. The platform never simulates impressions or clicks.

## SEO

- `App\Support\Seo` holds per-page title, description (160 chars), canonical, image, `noindex`, JSON-LD and hreflang alternates; rendered by `layouts/partials/seo.blade.php`. Alternates are derived from the current route for every locale plus `x-default` (the default locale).
- Game pages emit `VideoGame` JSON-LD (aggregate rating only with 3+ ratings); the home page emits `WebSite` with `SearchAction`. Filtered/sorted game listings canonicalise to `/games`.
- `/sitemap.xml` is an index of `/sitemaps/{locale}/{static|categories|games|pages}.xml` with hreflang alternates and image entries, cached 1 hour, containing only public games, active categories that have public games, and published pages.
- `/robots.txt` disallows admin, API, frames, account, search, rooms and ad clicks in `production`; in any other environment it disallows everything.
- Admin-managed 301/302 redirects (`HandleRedirects`) apply only when the app would otherwise return 404 for a GET.

## Admin and RBAC

`/admin/*` requires `auth` plus `permission:admin.access`; each section adds its own permission. `App\Enums\Role` maps roles to permissions (`super_admin` has `*`). `User::hasPermission()` returns false for suspended users. Sensitive actions are recorded through `App\Services\Audit::log()` into `audit_logs` (user, action, subject, JSON meta, IP). See `docs/ADMIN_GUIDE.md` for the full table.

## Imports

`app/Services/Import`: adapters (`manual`, `csv`, `json`, `gamedistribution`, registered in `config/platform.php`) read and map rows; `ImportNormalizer` validates licensing and normalises fields; `ImportService` previews into `import_items` and then creates draft games with unverified rights. See `docs/GAME_IMPORT_GUIDE.md`.

## Scheduled commands

Defined in `routes/console.php` (requires one cron entry running `php artisan schedule:run` every minute):

| Command | Schedule | Purpose |
|---|---|---|
| `stats:aggregate --days=2` | hourly, without overlapping | Daily stats, counters, popularity/trending scores, catalog flush |
| `platform:prune` | daily 03:30 | Expire/delete rooms, delete score sessions > 1 day, plays > 25 months, searches > 12 months |
| `games:smoke --untested` | daily 04:00, background | Real-browser launch test of games untested or not checked for 7 days (needs Node, Playwright, Chromium) |
| `queue:prune-failed --hours=168` | daily | Drop failed jobs older than 7 days |

## Front-end stack

- Vite 8 with `laravel-vite-plugin`; entries: `resources/css/app.css`, `resources/js/app.js`, `player.js`, `room.js`, `admin.js`.
- Tailwind CSS v4 through `@tailwindcss/vite` (`@import 'tailwindcss'` and `@source` lines in `app.css`).
- Fonts are self-hosted from npm (`@fontsource-variable/inter`, `@fontsource-variable/outfit`); no web font CDN.
- Alpine.js uses the CSP build (`@alpinejs/csp`) plus `@alpinejs/collapse`, so the page CSP needs no `unsafe-eval`. Components are registered with `Alpine.data(...)` in the JS entries. **Inline Alpine expressions in Blade must only reference members of a registered component** (properties, getters, methods); arbitrary inline JavaScript does not run under the CSP build. Add logic as a component method instead.
- Theme tokens (colors, fonts, radius, card style, density, default mode) come from the active `ThemeVersion` and are emitted as CSS variables by `layouts/partials/theme-vars.blade.php`.
- `npm run build` also runs `scripts/copy-vendor.mjs`, which copies `node_modules/@ruffle-rs/ruffle` (without source maps) to `public/vendor/ruffle`.
