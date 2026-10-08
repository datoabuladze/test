# Security

This document describes the security controls implemented in the code, how secrets and personal data are handled, how to report a vulnerability, and the known limitations. Nothing here has been verified by an external audit or penetration test.

## Reporting a vulnerability

Email the address configured as `CONTACT_EMAIL` (shown on the Contact and Copyright & takedown pages). Include the affected URL or file, steps to reproduce and impact. Please do not test against other users' accounts or data, run automated scanners at high volume, or publish details before a fix is available. There is no bug bounty.

## Content Security Policy and headers

`App\Http\Middleware\SecurityHeaders` (web group) sets on every response:

- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`
- `Cross-Origin-Opener-Policy: same-origin-allow-popups`
- `X-Frame-Options: SAMEORIGIN` (except game frames)
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` on HTTPS requests

and, for HTML responses, a nonce-based CSP:

```
default-src 'self';
script-src 'self' 'nonce-<per-request>' [+ AdSense / GA hosts only when configured];
style-src 'self' 'unsafe-inline';
img-src 'self' data: blob: https:;
font-src 'self' data:;
connect-src 'self' [+ GA / AdSense when configured];
frame-src 'self' [GAMES_ORIGIN] https:;
media-src 'self' blob:;
object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

The nonce comes from `Vite::useCspNonce()`, so Vite-built scripts and the few inline scripts (which carry `nonce="..."`) run, and nothing else does. Alpine.js is the CSP build, so no `unsafe-eval` is needed; Blade templates must not contain inline JavaScript outside component members. In `local` the Vite dev server origins are added. `style-src` allows `unsafe-inline` (Tailwind/Alpine style bindings and theme variables). `frame-src https:` is required for authorized third-party embeds; the embed host allow-list is enforced server-side instead.

CMS content (pages, game descriptions, instructions, controls) is rendered as Markdown with `html_input = strip` and unsafe links disabled; all other output uses Blade escaping.

## Game isolation

- **Sandbox.** The player iframe uses `sandbox="allow-scripts allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-forms"` without `allow-same-origin`. Game code runs in an opaque origin: it cannot read the page DOM, cookies, `localStorage` or make credentialed same-origin requests to the app.
- **Separate games origin.** With `GAMES_ORIGIN` set to a separate registrable domain, game files and frame documents are served from that domain, so even a sandbox bypass would not reach the app's origin or cookies. Without it, isolation relies on the sandbox only.
- **Frame documents** (`GameFrameController`) send their own CSP: `frame-ancestors 'self' APP_URL` (only the app can embed them), `object-src 'none'`, `base-uri 'none'`; scripts are limited to the app and games origins (`'unsafe-inline'` and `'wasm-unsafe-eval'` are allowed inside the frame because the Ruffle/Unity wrappers and wasm need them). Ruffle runs with `allowScriptAccess: false`, `allowNetworking: 'none'` and `openUrlMode: 'deny'`.
- **postMessage.** Both sides validate `event.source`; the page only acts on known message types, clamps error text and validates numbers. Scores from the frame are never trusted (see anti-cheat).
- **Non-public games.** Frame URLs for drafts are 30-minute signed URLs; a signature for one game does not unlock another (covered by `SecurityHeadersTest`).
- **Static serving.** nginx (and `scripts/dev-router.php`) never execute PHP or other script extensions under `/games/`, `/game-files/`, `/vendor/ruffle/`.

## Uploads

### Game packages (`App\Services\GamePackageInstaller`)

Only staff with `games.manage` can upload. Defences:

- Only `.zip` or `.swf` (size limit `MAX_GAME_PACKAGE_KB`, default 200 MB).
- SWF: checked for an `FWS`/`CWS`/`ZWS` signature, stored as `game.swf`.
- ZIP: every entry is checked before anything is written:
  - extension allow-list of static asset types (`config/platform.php` `uploads.allowed_package_extensions`); no server-side code can be written. Dotfiles are rejected except `.DS_Store`, `Thumbs.db` and `__MACOSX/` entries, which are skipped;
  - path checks: no empty names, NUL bytes, backslashes, absolute paths, drive letters, `..` segments, or names over 400 characters;
  - Unix symlink entries are rejected;
  - at most 5,000 files and 600 MB declared uncompressed size; while extracting, each file is also stream-limited to 600 MB to guard against lying size headers (note: this streaming check is per file, not cumulative);
  - an `index.html` must exist at the top level or in a single top-level folder.
- Files are extracted to a fresh random directory `public/game-files/<id>-<12 random chars>/`, served as static content only.

### Images (`App\Services\ImageProcessor`)

Game thumbnails (admin upload and imports) and user avatars are decoded with GD (JPEG, PNG, WebP, GIF; 16 px minimum, 40 megapixel maximum) and re-encoded to WebP with a random file name. Serving only re-encoded output strips metadata and defeats polyglot or script-in-image files. Ad banner images and branding logo/favicon are validated (PNG/JPEG/WebP by extension and MIME, max 1 MB, dimension limits) but stored as uploaded, not re-encoded; SVG is refused for them because SVG can contain scripts. Original-game thumbnails are repository SVGs.

## Rate limiting

Named limiters are defined in `AppServiceProvider::configureRateLimiting()`:

| Limiter | Limit | Used on |
|---|---|---|
| `api` | 120/min per user or IP | all `/api/*`, ad clicks |
| `login` | 5/min per email+IP, 20/min per IP | login |
| `register` | 10/hour per IP | registration |
| `password-email` | 3/min per IP | password reset request and submission |
| `verification` | 3/min | resend verification email |
| `reports` | 10/hour | game reports |
| `scores` | 10/min | score submission |
| `rooms` | 30/hour | room creation |

Email verification links additionally use `throttle:6,1`. The nginx template adds 20 req/s per IP on `/api/`. Client IPs come from `X-Forwarded-For` only for proxies in `TRUSTED_PROXIES`.

## Authentication

- **Passwords:** minimum 10 characters with letters and numbers (`Password::defaults()`); in production also checked against known breached passwords (`uncompromised()`, which calls the Have I Been Pwned range API). Stored with bcrypt (`hashed` cast).
- **Login:** a generic "credentials do not match" message; session regenerated on login; logout invalidates the session and regenerates the CSRF token.
- **Password reset:** the request always returns the same message whether or not the email exists. Reset tokens use Laravel's password broker. Changing the password from account settings logs out other devices.
- **Account enumeration:** login and password reset do not reveal whether an account exists. Registration and the email change form do reveal it (they show "email has already been taken"), which is the usual trade-off for a sign-up form.
- **Email verification:** users implement `MustVerifyEmail`; registration sends a verification link (signed, `throttle:6,1`), and changing the email resets verification. No route currently requires a verified email (there is no `verified` middleware), so unverified accounts can play, rate, favorite and submit scores.
- **Suspension:** suspended users cannot log in, are logged out on their next request (`TouchLastActive`), lose every permission (`User::hasPermission()` returns false) and are hidden from leaderboards.
- **Registration** can be closed in Admin > Settings.
- **Account deletion** requires the current password; favorites, ratings, scores, achievements and XP are deleted, plays are kept with `user_id` set to null.

## Authorisation and audit

- Every `/admin` route requires `auth` and `permission:admin.access`, plus a section permission (`EnsurePermission`, 403 otherwise). The role-permission map is `App\Enums\Role` (see `docs/ADMIN_GUIDE.md`).
- Sensitive operations are additionally checked in controllers: bulk publish/unpublish and permanent delete need `games.publish`; role changes need `users.manage`; only super admins can grant/revoke super admin or act on a super admin; staff cannot change their own account; moderators cannot act on staff.
- Changing a verified game's licensing fields resets its rights status; rejecting rights unpublishes.
- `App\Services\Audit::log()` records admin actions in `audit_logs` with user, action, subject, details and the **staff member's IP address**. The audit log is read-only in the UI (`audit.view`).

## CSRF

All state-changing routes (including `/api/*`, which is in `routes/web.php`) are protected by Laravel's CSRF middleware. The front-end sends `X-CSRF-TOKEN`. The play-status beacon (`navigator.sendBeacon` cannot set headers) sends `_token` inside its JSON body, and its URL is additionally a 6-hour signed URL bound to the play id. Session cookies use `SameSite=Lax`; the production example sets `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.

## Score anti-cheat

1. Server-issued, single-use, user- and game-bound score session tokens (180-minute lifetime).
2. Plausibility: claimed duration cannot exceed server-measured time (+5 s), score bounded by `max_score` and `max_points_per_second`, 10 submissions per minute.
3. Deterministic server-side replay for games with a verifier (currently Merge Orbit only): the run is re-simulated from the server seed and the move list; only exact matches are "verified".

Verified leaderboards hold only replayed scores. Casual leaderboards accept any score that passes plausibility.

## Multiplayer integrity

The server holds the game state and validates every move under a row lock. Seats are 48-character random tokens in httpOnly cookies compared with `hash_equals`; a forged cookie is treated as a spectator (covered by `MultiplayerRoomTest`).

## Outbound requests

The server makes outbound HTTPS requests only for: launch checks of embed URLs (only hosts on the provider allow-list), provider feeds (`feed_url` set by staff), import thumbnails (HTTPS, max 3 MB, max 2 HTTPS-only redirects), and the breached-password check. Feed and thumbnail URLs are not restricted to public IP ranges, so staff with `imports.manage` could point them at internal HTTPS services; treat that permission as trusted.

## Privacy

- **Visitors:** no raw IP or fingerprint is stored for visitors. Plays store `visitor_hash = sha256(VISITOR_HASH_SALT | date | IP | user agent)`, which changes daily and cannot be reversed. Country is read only from a CDN header (`CF-IPCountry`), never from an IP lookup. Search logs store the normalised query, locale and result count with no user id.
- **Reports:** game reports store `sha256(APP_KEY + IP)`. Unlike the visitor hash it does not rotate daily (it allows spotting repeated reports from one address), and the privacy policy page does not currently mention it.
- **Staff:** the audit log stores the acting staff member's IP address.
- **Retention:** `platform:prune` deletes raw plays after 25 months and search logs after 12 months (as stated in the privacy policy), score sessions after 1 day, and expired rooms after 1 day. Daily aggregates are kept.
- **Cookies:** the platform itself uses only essential cookies (session, CSRF, room seat cookies). The cookie banner appears only if GA4 or AdSense is configured; their scripts load only after "Accept all". GA4 is configured with `anonymize_ip`.

## Secrets

- Secrets live only in `.env` (production: `/var/www/nebulo/shared/.env`, mode 640, never committed). `.env` is git-ignored; `.env.example` and `deploy/env.production.example` contain no secrets.
- `APP_KEY` encrypts sessions and cookies and signs URLs. Rotating it logs everyone out and invalidates signed URLs and encrypted cookies. `VISITOR_HASH_SALT` defaults to `APP_KEY`; set it separately so the two can be rotated independently.
- The cache only unserializes an allow-list of classes (`config/cache.php` `serializable_classes`), limiting gadget-chain attacks if `APP_KEY` or the cache store were compromised.
- `ADMIN_PASSWORD` is only read by the seeder; prefer passing it on the command line for that one run and change the password after first login.
- Provider settings store feed URLs and licence defaults only; do not put API secrets there (the form has no secret fields).
- Rotation procedures are in `docs/OPERATIONS.md`.

## Known limitations

- **Multiplayer uses HTTP polling** (every 1.2 s). It is simple and robust but adds load and latency, and there is no matchmaking or anti-abuse beyond rate limits and room expiry.
- **Casual leaderboards can be gamed** within the plausibility limits: a client can submit any score up to `max_points_per_second x real elapsed time`. Only replay-verified boards (Merge Orbit) are cheat-resistant, and even those only prove the moves are legal, not that a human played them.
- **Cookie banner is not a certified CMP.** It stores a simple `consent` value in `localStorage` and does not implement IAB TCF. Google requires a Google-certified consent management platform for AdSense in the EEA, UK and Switzerland, so a certified CMP must be integrated before enabling AdSense for those visitors.
- **Email verification is not enforced** for any feature (see above).
- **Branding and ad images are not re-encoded** (validated only).
- **Uploaded package size check while streaming is per file**, so total extracted size is only bounded by the declared sizes and the per-file limit.
- **No automated vulnerability scanning** (dependency audit, SAST) is configured in CI.
- The nginx configuration is an unvalidated template (see `docs/DEPLOYMENT.md`).
