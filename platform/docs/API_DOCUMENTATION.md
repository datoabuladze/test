# API documentation

The platform has no public third-party API. The endpoints below are the internal JSON/HTTP endpoints used by the site's own front-end (`resources/js/*`), the game player and the game frames. They are all defined in `routes/web.php`; there is no `routes/api.php`.

## Conventions

- **Base path:** `/api`. All `/api` routes run the `web` middleware group (session cookie, CSRF), `locale.session` (locale from session, user or `Accept-Language`) and `throttle:api`.
- **CSRF:** every non-GET request needs the CSRF token, either in the `X-CSRF-TOKEN` header (the front-end reads it from `<meta name="csrf-token">`) or as `_token` in the body. Missing or wrong token: `419`.
- **Auth:** session cookie (log in through the normal login form). Endpoints marked "user" return `401 {"message": "Unauthenticated."}` for guests.
- **Format:** send `Content-Type: application/json` and `Accept: application/json`. Errors under `/api/*` are always JSON.
- **Route parameters:** `{slug}` is a game slug, `{code}` a room code, `{play}` a play id.
- **Visibility:** game endpoints return `404` for games that are not public (see `Game::scopePublic()`), except where noted.

### Rate limiters (`AppServiceProvider`)

| Name | Limit | Key |
|---|---|---|
| `api` | 120 / minute | user id, or IP for guests |
| `scores` | 10 / minute | user id or IP |
| `reports` | 10 / hour | user id or IP |
| `rooms` | 30 / hour | user id or IP |
| `login` | 5 / minute per email+IP and 20 / minute per IP | |
| `register` | 10 / hour | IP |
| `password-email` | 3 / minute | IP (also used for reset submission) |
| `verification` | 3 / minute | user id or IP |

Exceeding a limit returns `429` with `Retry-After`. In production, nginx additionally limits `/api/` to 20 requests/second per IP (burst 40).

### Error shapes

| Status | Body |
|---|---|
| 401 | `{"message": "Unauthenticated."}` |
| 403 | `{"message": "..."}` |
| 404 | `{"message": "..."}` (unknown or non-public game, unknown room) |
| 409 | `{"message": "This room is full."}` |
| 410 | `{"message": "This room has expired."}` |
| 419 | `{"message": "CSRF token mismatch."}` |
| 422 | `{"message": "...", "errors": {"field": ["..."]}}` (validation), or `{"message": "..."}` for invalid room moves |
| 429 | `{"message": "Too Many Attempts."}` |

## Endpoints

### Search suggestions

`GET /api/search/suggest?q=<text>` - guest, `throttle:api`

Returns up to 8 games and 4 categories for the live search box. Queries shorter than 2 characters after normalisation return empty lists. Response has `Cache-Control: public, max-age=60`.

```json
{
  "games": [{"title": "Merge Orbit", "url": "https://.../en/game/merge-orbit", "thumbnail": "/games/originals/merge-orbit/thumb.svg", "color": "#ec4899"}],
  "categories": [{"title": "Puzzle", "url": "https://.../en/category/puzzle"}],
  "corrected": null
}
```

`corrected` is the typo-corrected query when correction was applied (omitted for queries under 2 characters).

### Start a play

`POST /api/games/{slug}/plays` - guest or user, `throttle:api`

Records a play (`game_plays`: visitor hash, device, locale, country header, referrer host, user id if logged in), increments the game's play count and, for users, awards play XP. Allowed for public games, or any game for staff with `games.manage`.

Request body: none.

```json
{"play_id": 123, "status_url": "https://.../api/plays/123/status?expires=...&signature=..."}
```

### Update play status

`POST /api/plays/{play}/status?expires=...&signature=...` - guest or user, `throttle:api`, signed URL (valid 6 hours, from `status_url`)

| Field | Rules |
|---|---|
| `status` | required, `loaded`, `failed` or `ended` |
| `seconds` | optional integer 0-86400 |

`duration_seconds` keeps the maximum value reported. `loaded`/`failed` set the load status only while it is still `started`. Invalid or expired signature: `403`. The player sends `ended` with `navigator.sendBeacon`, which cannot set headers, so the CSRF token is sent as `_token` in the JSON body.

```json
{"ok": true}
```

### Report a game

`POST /api/games/{slug}/report` - guest or user, `throttle:api` + `throttle:reports`

| Field | Rules |
|---|---|
| `reason` | required: `not_loading`, `crashes`, `controls`, `inappropriate`, `copyright`, `other` |
| `message` | optional string, max 1000 |

Stores the report with the user id (if any) and a SHA-256 hash of `APP_KEY` + IP (no raw IP). `201`:

```json
{"ok": true, "message": "Thanks! We will look into it."}
```

### Game cards by id

`POST /api/games/by-ids` - guest or user, `throttle:api`

Renders cards for the "recently played" ids kept in the visitor's browser storage.

| Field | Rules |
|---|---|
| `ids` | required array, max 24 items |
| `ids.*` | integer |
| `variant` | optional `rail` (default) or `grid` |

Response: an **HTML fragment** (`partials/cards-rail` or `partials/cards-grid`) containing only public games, in the requested order; an empty `200` body if none are public.

### Toggle favorite

`POST /api/games/{slug}/favorite` - user, `throttle:api`

Request body: none. Toggles the favorite, updates the game's favorite count and evaluates achievements.

```json
{"favorited": true, "message": "Added to favorites"}
```

### Rate a game

`POST /api/games/{slug}/rating` - user, `throttle:api`

| Field | Rules |
|---|---|
| `stars` | required integer 1-5 |

Creates or updates the user's rating and recomputes the game's average.

```json
{"rating_avg": 4.33, "rating_count": 3, "message": "Thanks for rating!"}
```

### Start a score session

`POST /api/games/{slug}/score-session` - user, `throttle:api`

Only for public games with `score_mode` `casual` or `verified` (otherwise `404`). Issues a single-use token and a random seed; sessions expire after 180 minutes.

```json
{"token": "<48 characters>", "seed": 1234567890}
```

### Submit a score

`POST /api/games/{slug}/scores` - user, `throttle:api` + `throttle:scores`

| Field | Rules |
|---|---|
| `token` | required string, exactly 48 characters (from score-session) |
| `score` | required integer 0-1,000,000,000 |
| `duration_ms` | required integer 0-86,400,000 |
| `evidence` | optional object |
| `evidence.moves` | optional string, max 200,000 (Merge Orbit: `^[LRUD]+$`) |

Checks (see `docs/ARCHITECTURE.md`): token belongs to this user and game, unused, not expired; `duration_ms` not more than server-measured elapsed time + 5 s; score within `max_score` and `max_points_per_second`. Verified games replay the evidence from the session seed; an exact match makes the score verified, otherwise it is stored as casual. `201`:

```json
{"id": 77, "verified": true, "message": "Verified score saved!"}
```

`422` errors on `token`: `Invalid score session.`, `This score was already submitted.`, `Score session expired.`; on `score`: `This score could not be accepted.` A rejected submission does not consume the token. Only a count of the moves is stored, not the evidence itself.

### Multiplayer rooms

Seats are bound to an httpOnly cookie `room_<CODE>` (SameSite=Lax, lifetime 8 hours) holding a 48-character seat token. Clients without a valid seat cookie are spectators (`"you": null`).

Room payload returned by every room endpoint except create:

```json
{
  "code": "ABCDEF",
  "game": "tictactoe",
  "status": "playing",
  "version": 4,
  "you": 0,
  "state": {
    "round": 1,
    "score": [0, 0],
    "game": {"board": [0, null, null, null, 1, null, null, null, null], "turn": 0, "first": 0,
             "winner": null, "draw": false, "line": null, "moves": 2}
  },
  "host":  {"present": true, "ready": true},
  "guest": {"joined": true, "present": true, "ready": true},
  "expires_at": "2026-10-08T14:00:00+00:00"
}
```

`status` is `waiting`, `playing`, `finished` or `expired`. `you` is `0` (host), `1` (guest) or `null`. Board cells hold `0`, `1` or `null`; Connect Four uses `board[row][col]` (6x7, row 0 at the top) and adds `last: [row, col]`. `present` means the player polled within the last 20 seconds.

| Method and path | Auth / throttle | Request | Success | Errors |
|---|---|---|---|---|
| `POST /api/rooms` | guest or user; `api` + `rooms` | `game`: required, `tictactoe` or `connect4` | `201 {"code": "...", "url": "https://.../en/play-together/CODE"}` + host seat cookie | 422 |
| `POST /api/rooms/{code}/join` | guest or user; `api` | none | payload with `you: 1` + guest seat cookie (returns the current payload if you already hold a seat) | 409 full, 410 expired, 404 |
| `GET /api/rooms/{code}` | `api` | none | payload; also refreshes your presence and the room expiry | 410, 404 |
| `POST /api/rooms/{code}/ready` | seat holder; `api` | none | payload; the round starts when both are ready | 403 not a player, 410 |
| `POST /api/rooms/{code}/move` | seat holder; `api` | Tic-tac-toe: `cell` 0-8; Connect Four: `col` 0-6 | payload | 403, 410, 422 (`Not your turn.`, `Invalid cell.`, `Cell already taken.`, `Invalid column.`, `Column is full.`, `The game is over.`, `The game has not started.`) |
| `POST /api/rooms/{code}/rematch` | seat holder; `api` | none | payload; starts the next round (first player alternates) | 403, 410, 422 (`The round is still running.`) |

The client polls the state endpoint every 1.2 s (`resources/js/room.js`). Rooms expire 120 minutes after the last activity.

## Non-API endpoints

| Method and path | Auth / middleware | Behaviour |
|---|---|---|
| `GET /frame/{slug}` | public game, staff with `games.manage`, or valid relative signature (`?expires=&signature=`, 30-minute preview links) | Document for the sandboxed player iframe. `original`/`html5`/`phaser`: `302` to the static entry file on `GAMES_ORIGIN` (or the app origin); originals get `?lang=<locale>`. `ruffle`/`unity`: HTML wrapper page. `iframe`: `404`. Sends its own CSP (`frame-ancestors 'self' APP_URL`), `Cross-Origin-Resource-Policy: cross-origin`, `Cache-Control: public, max-age=300`, no `X-Frame-Options`. Non-public without permission: `404` |
| `GET /ad/{campaign}/click` | `throttle:api` | Records a click for the campaign and redirects: sponsored game -> the game page (`302`), direct banner -> its `http(s)` target (`302`). Inactive campaign or invalid target: `404` |
| `GET /sitemap.xml` | public | Sitemap index listing `/sitemaps/{locale}/{static,categories,games,pages}.xml` for every locale; cached 1 h |
| `GET /sitemaps/{locale}/{type}.xml` | public | `locale` in en/ka/tr/ru, `type` in games/categories/pages/static; URLs with `xhtml:link` hreflang alternates for every locale and `image:image` for game thumbnails; cached 1 h. Other types: `404` |
| `GET /robots.txt` | public | In `production`: disallows `/admin`, `/api/`, `/frame/`, `/*/account`, `/*/search`, `/*/play-together/`, `/ad/`. In any other environment: `Disallow: /`. Always lists the sitemap index |
| `GET /up` | public | Laravel health check (`200` when the app boots) |

## Game frame protocol (postMessage)

Games run in an iframe sandboxed without `allow-same-origin` (`sandbox="allow-scripts allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-forms"`). The page (`resources/js/player.js`) and the frame talk only through `window.postMessage`. Both sides check `event.source` (the page accepts messages only from its own iframe; the SDK only from `window.parent`). Messages are plain objects with a string `type`.

### Frame to page

| Message | Effect |
|---|---|
| `{type: 'nebulo:ready'}` | Game is loaded. Required for engines `original`, `unity`, `ruffle`; without it the player shows an error after 30 s. For uploaded `html5`/`phaser` packages and `iframe` embeds the frame `load` event is used instead (they may still send it). On ready the page sends `nebulo:mute` and, if a score session exists, `nebulo:session`, and reports `loaded` |
| `{type: 'nebulo:score', score}` | Live score for the HUD (`score` must be a finite number) |
| `{type: 'nebulo:gameover', score, durationMs, evidence}` | Final score. For logged-in users the page submits it with the current score token (then requests a new session). `evidence` must be an object or `null` |
| `{type: 'nebulo:error', message}` | Shows an error (message truncated to 200 chars) and reports `failed` |

### Page to frame

| Message | Effect in the SDK |
|---|---|
| `{type: 'nebulo:mute', muted}` | Mute or unmute audio |
| `{type: 'nebulo:pause'}` | Sent when the tab is hidden; the SDK pauses a running game |
| `{type: 'nebulo:resume'}` | Sent when the tab is visible again; the SDK deliberately stays paused until the player resumes |
| `{type: 'nebulo:session', seed}` | Server-issued integer seed; the SDK uses it for the next round's RNG so verified games can be replayed on the server |

The page posts with target origin `'*'` because the frame's origin is opaque; it only sends these UI commands, never user data. Ruffle and Unity wrapper pages (`resources/views/frames/*.blade.php`) implement `ready`, `error` and `mute` (Ruffle also pause/resume) themselves. Third-party HTML5/Phaser bundles do not need to know the protocol; they count as ready on load.

## Original-game SDK (brief)

`public/games/originals/_sdk/sdk.js` defines `window.NebuloGame`. Full reference: `public/games/originals/_sdk/README.md`.

```js
var game = NebuloGame.create({ id: 'my-game', title: 'My Game', tagline: '...', howTo: ['...'], pausable: true });
var view = game.canvas(640, 480);          // HiDPI canvas in logical units
game.on('start', function () { /* reset round state; game.rng() is seeded */ });
game.loop(function update(dt) {}, function render(dt) {});   // fixed 60 Hz update while playing
game.setStat('score', 10);                 // HUD + live nebulo:score
game.over({ score: 10, win: false, evidence: null });        // game-over card + nebulo:gameover
game.showMenu(); game.ready();             // always last: shows menu, posts nebulo:ready
```

Other members: `game.keys`, `game.touchButtons()`, `game.onSwipe()`, `game.overlay()`, `game.elapsed()`, `game.audio.sfx()/tone()`, `game.store.get()/set()`, `game.t()`, `NebuloGame.lang` (from `?lang=` or the browser language), `NebuloGame.mulberry32`. Uncaught errors are reported as `nebulo:error`. States: `menu -> playing <-> paused -> over`.
