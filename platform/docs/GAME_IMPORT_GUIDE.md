# Game import guide

This guide covers bulk imports of third-party games (CSV, JSON, provider feeds), adding a single game by hand, and adding a first-party ORIGINAL game.

**Licensing rule:** every game on the platform must have documented rights. The importer never makes anything public. Imports create **draft** games with **unverified** rights; a staff member must review each one (rights review, launch check, preview) and publish it through the normal gate (see `docs/ADMIN_GUIDE.md`). Do not import from sources you are not licensed to use, and do not scrape or mirror other sites.

## Pipeline

```
upload / feed  ->  adapter.rows()  ->  adapter.map()  ->  ImportNormalizer.normalize()
                                                          |  errors -> item "invalid"
                                                          |  duplicate -> item "duplicate"
                                                          v
                                    import_items (status "valid")  -- PREVIEW, catalog untouched
                                                          |
                     staff selects rows + confirms provider agreement allows listing
                                                          v
                         ImportService.run()  ->  Game: status=draft, rights=unverified,
                                                  launch_status=untested
```

1. **Create a batch** (Admin > Imports, permission `imports.manage`). Source is `csv`, `json` (file upload, max 20 MB, `.csv`/`.txt`/`.json`) or `provider` (reads the provider's feed via its adapter). Choosing a provider for a file import applies that provider's allow-list and defaults.
2. **Preview** (`PreviewImportBatch`). Every row is parsed, mapped, validated and stored in `import_items` with status `valid`, `invalid` or `duplicate`, plus its errors and warnings. Nothing is written to `games`. Files up to 2 MB are previewed during the request; larger files and provider feeds are queued (a queue worker must run). Max 5,000 rows per batch.
3. **Review** (Admin > Imports > batch). Filter by status, read errors and warnings per row. "Rebuild preview" re-runs validation (useful after fixing provider settings).
4. **Run** (`RunImportBatch`). Choose "selected rows" or "all valid rows" and tick the confirmation that the provider agreement allows these games to be listed. Up to 300 rows run during the request; more are queued. Each valid item becomes a draft game; the item status becomes `imported` (or `failed` with the error). The run is recorded in the audit log and flushes the catalog cache.
5. **Per-game review.** For each new draft: check licence and source, upload files for self-hosted engines, run the launch check, preview, verify rights, then publish.

Batch statuses: `queued`, `running`, `previewed`, `completed`, `failed`. Item statuses: `pending`, `valid`, `invalid`, `duplicate`, `imported`, `failed`.

## Canonical fields

Rows use the field names in `App\Services\Import\ImportNormalizer::FIELDS`. Header names are case-insensitive. Unknown columns are kept in the raw data but ignored.

| Field | Type / allowed values | Notes |
|---|---|---|
| `external_id` | string, max 191 | Provider's id; used for duplicate detection |
| `title` | string, max 120 | **Required** (English). Also accepted as `title_en` |
| `short_description` | string | |
| `description` | string, max 10,000 | HTML tags are stripped |
| `instructions` | string | |
| `controls` | string | |
| `engine` | `iframe` (default), `html5`, `phaser`, `unity`, `ruffle` | `original` is rejected |
| `embed_url` | HTTPS URL | Required for `iframe`; host must be on the provider allow-list. Ignored for self-hosted engines |
| `thumbnail_url` | http(s) URL | Downloaded only if `thumbnail_rights` is true and the URL is HTTPS (max 3 MB, re-encoded to WebP 640x480) |
| `categories` | list of category slugs or names | Slugified and matched against existing categories; unknown ones are ignored with a warning. The first known one becomes primary |
| `tags` | list | Max 15, 40 chars each; created if missing (English name) |
| `width`, `height` | integer | Clamped to 200-4096 |
| `orientation` | `any`, `landscape`, `portrait` | Default `any` |
| `devices` | list of `desktop`, `tablet`, `mobile` | |
| `input_types` | list of `keyboard`, `mouse`, `touch`, `gamepad` | |
| `languages` | list of `en`, `ka`, `tr`, `ru` | |
| `developer` | string, max 191 | |
| `developer_url` | http(s) URL | |
| `source_url` | http(s) URL | **Required.** Where the game/licence comes from |
| `license_type` | string, max 64 | **Required** (row or provider default), e.g. `Provider agreement`, `CC BY 4.0` |
| `license_url` | http(s) URL | |
| `attribution_text` | string, max 2,000 | |
| `hosting_method` | `self_hosted` or `iframe_embed` | Defaults to `iframe_embed` for `iframe` rows |
| `commercial_use_allowed`, `ads_allowed`, `modifications_allowed`, `thumbnail_rights`, `embed_authorized` | boolean | |
| `min_age` | integer 0-18 | Default 0 |
| `is_mobile_friendly`, `is_multiplayer` | boolean | |

### Per-locale fields

The translatable fields (`title`, `short_description`, `description`, `instructions`, `controls`) take a locale suffix for the other languages: `title_ka`, `title_tr`, `title_ru`, `description_ru`, and so on. The unsuffixed column (or `_en`) is English.

### Lists and booleans

- List fields (`categories`, `tags`, `devices`, `input_types`, `languages`) accept `|` or `,` as separators in CSV, or a JSON array in JSON. In CSV, prefer `|` so the cell does not need quoting.
- Booleans use PHP's `FILTER_VALIDATE_BOOL`: `1`, `true`, `yes`, `on` are true; anything else (including empty) is false.
- Empty cells are treated as missing, so provider defaults can fill them.

## Validation rules

A row is **invalid** (not importable) when any of these fail:

| Rule | Error |
|---|---|
| English title present | `Missing English title.` |
| `engine` is one of iframe/html5/phaser/unity/ruffle | `Unsupported engine ...` |
| For `iframe`: `embed_url` is an `https://` URL with a host | `Embed URL must be an HTTPS URL.` |
| For `iframe`: the host is allow-listed by the batch's provider (exact host or `*.example.com` wildcard for subdomains). A batch without a provider fails this check | `Embed host ... is not on the provider's allow-list.` |
| `license_type` present (row or provider default) | `Missing license type ...` |
| `source_url` present and a valid http(s) URL | `Missing source URL.` |
| `hosting_method` is `iframe_embed` for iframe rows | `Hosting method must be iframe_embed for embedded games.` |
| `hosting_method` is `self_hosted` for other engines (the licence must allow redistribution) | `Self-hosted games need hosting_method=self_hosted ...` |
| For `iframe`: `embed_authorized` is true (the provider permits embedding) | `embed_authorized must be true ...` |

Warnings (row stays valid): invalid optional URLs ignored, thumbnail skipped because `thumbnail_rights` is false, unknown categories ignored, no known category, and for self-hosted engines a reminder to upload files after import.

## Duplicate detection

A valid row is marked `duplicate` (and not imported) if any of these match an existing game, including trashed ones:

1. same provider and `external_id`;
2. same `embed_url` (any provider);
3. same slug as the slugified English title.

Rows within the same file are also compared, keyed by `external_id`, else `embed_url`, else title slug; the second and later occurrences are duplicates. The item notes `Duplicate of game #<id>` where applicable. On import, if the slug is taken by then, a random 4-character suffix is added.

## Provider defaults

Admin > Providers stores, per provider:

- **Adapter** (`manual`, `csv`, `json`, `gamedistribution`), website and agreement URLs, agreement notes, active flag;
- **Allowed embed hosts** (comma or space separated; `*.cdn.example.com` allows subdomains);
- **Defaults** applied when a row leaves the field blank: `license_type`, `license_url`, `hosting_method`, and the flags `commercial_use_allowed`, `ads_allowed`, `modifications_allowed`, `thumbnail_rights`, `embed_authorized` (only "true" can be set as a default);
- **Feed URL** (HTTPS) for feed adapters.

Note that `source_url` cannot be set as a provider default from the admin form, so every row must carry its own `source_url`.

A provider with games cannot be deleted; deactivate it instead.

## Adapters

| Key | Class | Source |
|---|---|---|
| `manual` | `ManualAdapter` | No bulk source; games are added one by one in Admin > Games. Running a feed import for it fails with an explanatory message |
| `csv` | `CsvAdapter` | Uploaded CSV with a header row. UTF-8 BOM stripped; delimiter `;` is used if the header has more semicolons than commas; blank lines skipped |
| `json` | `JsonAdapter` | Uploaded JSON: an array of objects, or `{"games": [...]}`; non-object entries are skipped; max nesting depth 64 |
| `gamedistribution` | `GameDistributionAdapter` | Provider feed (see below) |

For `csv`/`json` sources the file adapter is used regardless of the provider's adapter; for `provider` sources the provider's adapter is used.

### GameDistributionAdapter

Reads a JSON catalog from the provider's HTTPS `feed_url` (20 s timeout, max 50 MB; the provider must be active) and maps common export field names (`Md5`/`Id`, `Title`, `Description`, `Instructions`, `Url`, `Asset`, `Category`, `Tag`, `Width`, `Height`, `Mobile`, `Company`) to canonical fields with `engine = iframe`. Licensing comes from the provider record, not the feed.

- **The field mapping is unverified.** It follows the provider's documented export format but has not been tested against a live feed in this repository. Always check the preview before running an import.
- **Use it only with an approved publisher account and within the provider's terms**, including their rules on embedding, ads and thumbnails. Allow-list only the provider's game hosts.
- `source_url` is taken from the feed's game page field (`GameUrl`/`Link`) and falls back to the embed `Url`. The mapping has not been tested against a live feed.

## Sample CSV

```csv
external_id,title,title_ka,title_tr,title_ru,short_description,engine,embed_url,thumbnail_url,categories,tags,width,height,orientation,devices,input_types,developer,source_url,license_type,license_url,hosting_method,ads_allowed,thumbnail_rights,embed_authorized,is_mobile_friendly
ex-101,Crystal Caves,,,Кристальные пещеры,Dig through glowing caves.,iframe,https://html5.example-provider.com/games/ex-101/,https://html5.example-provider.com/thumbs/ex-101.png,adventure|puzzle,caves|mining,800,600,landscape,desktop|tablet|mobile,keyboard|touch,Example Studio,https://example-provider.com/games/ex-101,Provider agreement,https://example-provider.com/terms,iframe_embed,1,1,1,1
ex-102,Orbit Racer,,,,Race around tiny planets.,iframe,https://html5.example-provider.com/games/ex-102/,,racing,space,960,540,landscape,desktop,keyboard,Example Studio,https://example-provider.com/games/ex-102,Provider agreement,,iframe_embed,1,0,1,0
```

With a provider whose defaults include `license_type`, `hosting_method = iframe_embed` and `embed_authorized`, the licence columns can be left out.

## Sample JSON

```json
{
  "games": [
    {
      "external_id": "ex-201",
      "title": "Garden Match",
      "title_tr": "Bahçe Eşleştirme",
      "description": "Match flowers in rows of three.",
      "engine": "iframe",
      "embed_url": "https://html5.example-provider.com/games/ex-201/",
      "categories": ["puzzle", "casual"],
      "tags": ["match-3", "flowers"],
      "devices": ["desktop", "tablet", "mobile"],
      "input_types": ["mouse", "touch"],
      "developer": "Example Studio",
      "source_url": "https://example-provider.com/games/ex-201",
      "license_type": "Provider agreement",
      "hosting_method": "iframe_embed",
      "embed_authorized": true,
      "ads_allowed": true,
      "is_mobile_friendly": true
    }
  ]
}
```

A self-hosted row (for a game whose licence permits redistribution) uses e.g. `"engine": "html5"`, `"hosting_method": "self_hosted"` and no `embed_url`; upload the ZIP on the game page after import.

## Adding a new adapter

1. Create a class in `app/Services/Import/Adapters/` implementing `App\Services\Import\ImportAdapter`:
   - `label()`: name shown in the admin;
   - `needsFile()`: `true` for uploaded files, `false` for remote feeds;
   - `rows(ImportBatch $batch)`: yield raw rows (arrays). Read feed settings from `$batch->provider->settings`. Throw `ImportException` with a readable message when the source cannot be read. Use HTTPS, timeouts and size limits (see `GameDistributionAdapter`);
   - `map(array $raw)`: return canonical fields (keys from `ImportNormalizer::FIELDS`, with locale suffixes as needed). Do not validate or create games here: validation, duplicate detection and game creation are shared so every source passes the same licensing gate.
2. Register it in `config/platform.php` under `import.adapters` with a short key. It then appears in the provider form.
3. Add a feature test in `tests/Feature/Admin/ImportTest.php` (use `Http::fake()` for feeds).
4. Only build adapters for sources you have a written agreement with.

## Adding a single third-party game by hand

Admin > Games > New: fill the licence section completely, upload a ZIP/SWF or enter an allow-listed embed URL, then follow the workflow in `docs/ADMIN_GUIDE.md`.

Self-hosted HTML5 or Phaser bundles need no changes: the player and the browser launch test (`games:smoke`) treat them as ready when the frame finishes loading, then watch for runtime errors. Bundles may still post `{type: 'nebulo:ready'}`, but it is not required.

## Adding an ORIGINAL game

Original games are first-party works written for this platform and shipped in the repository.

1. Create `public/games/originals/<key>/` (the key is lowercase with dashes and doubles as the slug):

   | File | Purpose |
   |---|---|
   | `index.html` | Loads `../_sdk/sdk.css`, `../_sdk/sdk.js`, then `game.js`. Classic scripts only (no ES modules: frames have an opaque origin and modules would need CORS) |
   | `game.js` | The game in an IIFE with `'use strict'`. No network requests, CDNs or web fonts |
   | `thumb.svg` | Original 640x480 vector cover art; no third-party characters or logos |
   | `meta.json` | Catalog metadata |

2. Build on the SDK (`window.NebuloGame`; full cheat sheet in `public/games/originals/_sdk/README.md`). Always end setup with `game.showMenu(); game.ready();`, reset round state in `game.on('start')`, use `game.rng()` instead of `Math.random()` for gameplay, report the score with `game.setStat('score', n)` and finish with `game.over({score, ...})`. Support `en`, `ka`, `tr`, `ru` (`NebuloGame.lang`).
3. Write `meta.json` following `public/games/originals/merge-orbit/meta.json`:

   | Key | Notes |
   |---|---|
   | `key`, `slug` | Folder name; slug defaults to key |
   | `title`, `short_description`, `description`, `instructions`, `controls` | Objects keyed `en`, `ka`, `tr`, `ru`; descriptions are Markdown |
   | `categories`, `primary_category` | Existing category slugs |
   | `tags` | `[{"slug": "...", "name": {"en": ..., "ka": ..., "tr": ..., "ru": ...}}]` |
   | `input_types`, `devices`, `orientation`, `difficulty`, `session_length`, `min_age` | As in the canonical fields |
   | `score_mode` | `none`, `casual` or `verified` |
   | `verifier` | For `verified`: class implementing `App\Services\Verifiers\ScoreVerifier` |
   | `max_points_per_second`, `max_score` | Plausibility limits (stored in `engine_config`) |
   | `color`, `width`, `height` | Card color and design size |
   | `is_multiplayer`, `is_mobile_friendly`, `featured`, `editors_pick` | Flags |

4. Register it: `php artisan db:seed --class=OriginalGamesSeeder`. The seeder registers every folder that has `meta.json` and `index.html` as `engine = original`, provider "Originals", licence "Original work", hosting `self_hosted`, rights verified, status published, `launch_status` untested; it adds the `html5` category (and `mobile` if mobile-friendly), syncs tags and flushes the catalog cache. Re-running updates existing originals.
5. Test it: `node tests/browser/original-game-smoke.mjs <key>` (desktop and mobile viewports, checks for runtime errors and the ready message), then `php artisan games:smoke --game=<key>` to record `launch_status = ok`.
6. Add the game to `docs/GAME_LICENSES.md`.

A verified-score game also needs a server-side `ScoreVerifier` that deterministically replays the run from the session seed and the evidence the game sends in `game.over({evidence})`; see `MergeOrbitVerifier` and its unit test.
