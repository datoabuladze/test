# Game and asset licences

This document records the licensing status of every game shipped in the repository and of the third-party software the platform depends on. It reflects the repository on 2026-10-08. Update it whenever a game or dependency is added.

## Original games

All games below live in `public/games/originals/<key>/` and are registered by `database/seeders/OriginalGamesSeeder.php` with provider "Originals", licence type "Original work", hosting `self_hosted` and rights status `verified`.

They are original works created for this project: the game code (`game.js`), the shared SDK (`_sdk/sdk.js`, `_sdk/sdk.css`) and the cover art (`thumb.svg`, hand-written SVG) were written for it. They contain no third-party assets: no bundled images other than the SVG cover, no audio files (sound effects are synthesised at runtime with the Web Audio API through the SDK), no web fonts (system font stacks only), and no third-party libraries.

| Key | English title | Categories | Score mode |
|---|---|---|---|
| `block-cascade` | Block Cascade | puzzle, classic, logic, retro | casual |
| `brick-breaker` | Prism Breaker | arcade, classic, action, casual | casual |
| `color-rush` | Chroma Clash | brain, casual, arcade | casual |
| `four-in-a-row` | Gravity Four | board, strategy, two-player, logic, classic | casual |
| `goal-rush` | Goal Rush | sports, football | casual |
| `hoop-shot` | Hoop Shot | sports, basketball, arcade | casual |
| `math-sprint` | Math Sprint | educational, brain, kids, casual | casual |
| `memory-match` | Neon Pairs | puzzle, brain, casual, kids | casual |
| `merge-orbit` | Merge Orbit | puzzle, logic, brain, casual | verified (`MergeOrbitVerifier`) |
| `mine-sweeper` | Mine Field | puzzle, logic, strategy, classic | casual |
| `neon-snake` | Neon Snake | arcade, classic, retro, casual | casual |
| `pixel-leap` | Pixel Leap | platformer, adventure, action | casual |
| `reflex-test` | Pulse Reflex | brain, casual, action | casual |
| `sky-dash` | Sky Dash | endless-runner, platformer, arcade, casual | casual |
| `slide-15` | Tile Drift | puzzle, logic, brain, classic | casual |
| `stack-tower` | Stack Tower | arcade, casual, building | casual |
| `star-defender` | Star Defender | shooting, action, arcade, retro | casual |
| `sudoku-zen` | Sudoku Zen | puzzle, logic, brain, classic | casual |
| `tic-tac-toe` | Neon Noughts | board, logic, two-player, classic, casual | casual |
| `turbo-lanes` | Turbo Lanes | racing, car, driving, arcade | casual |
| `word-hunt` | Hidden Words | puzzle, educational, brain, casual | casual |

21 games. The platform also includes two server-side multiplayer rule sets (Tic-tac-toe and Connect Four in `app/Services/Multiplayer/`), written for this project.

### Verification performed

For every game folder and `_sdk/`:

- `grep` for `http://` / `https://` in `.js`, `.html`, `.css`, `.json`, `.svg`: the only match is the SVG namespace URI `http://www.w3.org/2000/svg` in each `thumb.svg` (an XML namespace identifier, not a network request).
- Every `index.html` loads exactly three local files: `../_sdk/sdk.css`, `../_sdk/sdk.js`, `game.js`. No `import`, `require`, `fetch`, `XMLHttpRequest`, `WebSocket`, `@import`, `url(...)` or external `<link>`/`<script>` was found.
- No embedded raster images (`data:image`, base64, `<image>`, `xlink:href`) and no audio/image files are referenced.
- `meta.json` exists for every game folder; the table above was generated from those files.

These checks show the games do not load external resources; they cannot by themselves prove authorship. Authorship rests on the games having been written for this repository.

Gameplay notes: several games implement well-known public game genres (falling blocks, brick breaker, snake, sudoku, minesweeper, 15-puzzle, 2048-style merging, word search, four-in-a-row, tic-tac-toe). Game mechanics are not protected by copyright, but names, logos and distinctive trade dress can be protected by trademark. The games use their own names and art and do not use names such as Tetris, 2048 or Minesweeper in titles; keep it that way. Word lists in Hidden Words are common dictionary words written for the game.

Rights holder: the platform operator (`LEGAL_OPERATOR`). The licence notes stored on each game read "Code, art and sound created for this platform. All rights reserved by the operator."

## Self-hosted runtime: Ruffle

| Component | Version | Licence | Source |
|---|---|---|---|
| Ruffle Flash Player emulator | `@ruffle-rs/ruffle` 0.7.1 (npm) | MIT OR Apache-2.0 (dual) | Copied from `node_modules` to `public/vendor/ruffle` by `scripts/copy-vendor.mjs` during `npm run build` |

Ruffle is self-hosted: no Adobe Flash Player and no Ruffle CDN are used. The package's `LICENSE_MIT` and `LICENSE_APACHE` files are copied along with it. Ruffle is only the emulator; every SWF it plays needs its own documented licence (see the policy below).

## Fonts

| Font | Package | Licence |
|---|---|---|
| Inter (variable) | `@fontsource-variable/inter` 5.3.0 | SIL Open Font License 1.1 |
| Outfit (variable) | `@fontsource-variable/outfit` 5.3.0 | SIL Open Font License 1.1 |

Both are bundled by Vite from npm (`@import` in `resources/css/app.css`) and served from the app origin; no Google Fonts or other font CDN is used. The OFL allows bundling and use on websites; the fonts may not be sold on their own.

## Other dependencies

### npm (`package.json`)

| Package | Use | Licence |
|---|---|---|
| `@alpinejs/csp`, `@alpinejs/collapse` 3.17.4 | Front-end interactivity (shipped to browsers) | MIT |
| `@fontsource-variable/*` | Fonts (shipped) | OFL-1.1 |
| `@ruffle-rs/ruffle` | Flash emulator (shipped) | MIT OR Apache-2.0 |
| `vite`, `laravel-vite-plugin`, `tailwindcss`, `@tailwindcss/vite` | Build tooling | MIT |
| `@playwright/test` | Tests only | Apache-2.0 |
| `concurrently`, `typescript`, `@laravel/multiplex` (optional) | Development tooling | MIT / Apache-2.0 |

A scan of the installed `node_modules` found only permissive licences (MIT, ISC, Apache-2.0, BSD, 0BSD, `MIT OR CC0-1.0`) plus OFL-1.1 for the fonts and MPL-2.0 for `lightningcss` (a build-time CSS tool used by Tailwind/Vite; not shipped to browsers, and MPL-2.0 only imposes obligations on modified MPL files).

### Composer (`composer.json`, `composer.lock`)

Runtime: `laravel/framework` ^13.17 and `laravel/tinker` (MIT). Development: `fakerphp/faker`, `laravel/pail`, `laravel/pao`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit`.

Of the 77 locked runtime packages, 69 are MIT; the rest are BSD-3-Clause (`league/commonmark`, `league/config`, `nikic/php-parser`, `tijsverkoyen/css-to-inline-styles`, `vlucas/phpdotenv`), Apache-2.0 (`phpoption/phpoption`) and `BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only` (`nette/schema`, `nette/utils`, used under BSD-3-Clause). All allow commercial use. Keep their notices if you redistribute the code.

## Policy for third-party games

1. **Only rights-verified games are published.** A game becomes public only when a staff member has verified a documented licence (licence type, source, permitted hosting method, embed permission or redistribution right, thumbnail rights) and it passes the publish gate (`GamePublisher`) and `Game::scopePublic()`. Imports always create unverified drafts.
2. **No scraping or mirroring.** Games are added only from sources the operator has an agreement or explicit licence with: provider feeds under an approved account and within the provider's terms, files supplied by the developer, or works under an open licence that permits the chosen hosting method. The importer has no crawler.
3. **No automatic downloading of Flash games.** SWF files are uploaded manually by staff, one at a time, after the redistribution right has been documented. Flash games stay hidden until tested in Ruffle and marked Compatible or Partially compatible.
4. **Embeds stay on the provider's domain.** Embedded games load from HTTPS hosts on the provider's allow-list, with `embed_authorized` confirmed. Changing licence fields resets the rights review.
5. **Attribution.** Where a licence requires it, fill `attribution_text`; it is stored with the game.

## Takedown process

Rights holders contact `CONTACT_EMAIL` (published on the "Copyright & takedown" page, `/{locale}/p/takedown`) or use the Report button on the game page with reason "Copyright or license concern". Staff then:

1. **Unpublish first** (Admin > Games > Unpublish, or Rights review > Rejected, which also unpublishes). This is recorded in the audit log.
2. Note the notice in the game's rights review note (date, sender, summary) and mark related reports resolved.
3. Acknowledge receipt to the sender (the published page promises about two business days) and, for provider-supplied games, forward the notice to the provider.
4. Restore (re-verify rights and publish) only with documented evidence that the use is licensed, or move the game to trash and delete it permanently if not. Delete the uploaded files under `public/game-files/` manually after a permanent delete.

The full operational procedure is in `docs/OPERATIONS.md`.
