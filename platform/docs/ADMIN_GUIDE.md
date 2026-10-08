# Admin guide

The admin panel is at `/admin`. It requires a logged-in, non-suspended account whose role has the `admin.access` permission. The sidebar only shows sections your role can use; opening a section without the permission returns 403. Most changes are written to the audit log.

## Roles and permissions

Roles are defined in `App\Enums\Role`. A suspended user loses every permission regardless of role.

| Permission | super_admin | admin | game_manager | content_editor | moderator | analytics_viewer | player |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `admin.access` (open the panel, dashboard) | yes | yes | yes | yes | yes | yes | - |
| `games.manage` (games list, create, edit, upload, launch check, preview, bulk, trash) | yes | yes | yes | - | - | - | - |
| `games.publish` (publish, unpublish, schedule, permanent delete) | yes | yes | yes | - | - | - | - |
| `games.rights` (rights review decision) | yes | yes | yes | - | - | - | - |
| `categories.manage` (categories, tags) | yes | yes | yes | yes | - | - | - |
| `imports.manage` (providers, imports) | yes | yes | yes | - | - | - | - |
| `reports.manage` (player reports) | yes | yes | yes | - | yes | - | - |
| `content.manage` (pages and blog, menus, homepage) | yes | yes | - | yes | - | - | - |
| `seo.manage` (SEO overview, redirects) | yes | yes | - | yes | - | - | - |
| `design.manage` (theme versions, logo, favicon) | yes | yes | - | - | - | - | - |
| `ads.manage` (placements, campaigns) | yes | yes | - | - | - | - | - |
| `users.moderate` (user list, suspend, unsuspend) | yes | yes | - | - | yes | - | - |
| `users.manage` (change roles, act on staff accounts) | yes | yes | - | - | - | - | - |
| `analytics.view` | yes | yes | yes | - | - | yes | - |
| `audit.view` | yes | yes | - | - | - | - | - |
| `settings.manage` (settings, translations) | yes | yes | - | - | - | - | - |

`super_admin` has the wildcard `*`. Only a super admin can grant or remove the `super_admin` role or act on a super admin's account.

The first super admin is created by `AdminUserSeeder` from `ADMIN_EMAIL` / `ADMIN_PASSWORD` (see `docs/INSTALLATION.md`).

## Dashboard

Counts of public games, total games, drafts, unverified rights, failed and untested launches, plays and failed loads in the last 24 h, users, and open reports. Lists top games by 24 h plays, games needing attention (failed launch or unverified rights), open reports, recent imports and recent audit activity.

## Games

### List

Search by title/slug/tags (`search_text`) or exact external id; filter by status, rights status, launch status, engine, provider, category; sort by last updated, plays, title or publish date. The **Trash** toggle shows soft-deleted games.

### Workflow

A game becomes visible only through the publish gate (`GamePublisher`) and the visibility rules in `Game::scopePublic()`. The intended workflow:

1. **Create draft** (Games > New). New games are always `draft` with rights `unverified`. Required: English title, engine, orientation, score mode. Fill licensing fields in **Source & license**: provider, source URL, licence type and URL, licence notes, attribution, hosting method (`self_hosted` or `iframe_embed`), and the flags commercial use / ads allowed / modifications allowed / thumbnail rights / embed authorized.
2. **Add files.**
   - Self-hosted (HTML5, Phaser, Unity, Flash): upload a package in **Files & launch check**. Accepted: a `.zip` of static files with `index.html` at the top level or inside a single top-level folder, or a single `.swf`. The engine is detected (Unity if `Build/*.loader.js` exists, Phaser if `index.html` mentions "phaser", otherwise HTML5; `.swf` = Ruffle). Upload resets the launch status to `untested` (and Flash compatibility to `untested`). Allowed file types, size and count limits are in `config/platform.php`; server-side code (`.php` etc.) is rejected.
   - Embedded (`iframe`): enter an HTTPS embed URL. Its host must be on the provider's allow-list.
   - Alternatively set `entry_path` manually (must start with `games/` or `game-files/`).
3. **Launch check** (button "Run launch check"). Confirms the entry file exists and looks like HTML (or a valid SWF signature, or all Unity build files), or that an embed URL is reachable and does not forbid framing. A failure sets `launch_status = failed`, which blocks publishing and hides a published game. A pass sets `untested` (unless it was already `ok`); only the browser smoke test (`php artisan games:smoke`) sets `ok`.
4. **Preview.** Opens the game in the real sandboxed player using a 30-minute signed frame URL. Previews do not record plays or scores.
5. **Rights review** (`games.rights`). Choose Verified, Rejected or Unverified, with an optional note (appended to licence notes with date and your nickname). Verifying requires ticking the confirmation and having a licence type and (for non-originals) a source URL. Rejecting or resetting rights on a published game unpublishes it. Editing any licensing field later (licence type/URL, source URL, hosting method, embed URL, provider, or any rights flag) on a verified game automatically resets rights to `unverified`, which hides it until re-verified.
6. **Publish or schedule** (`games.publish`). The Publishing panel lists all blockers. "Publish" sets `published` with `published_at = now`; entering a future date schedules it (the game appears automatically at that time). "Unpublish" hides it immediately.

Publish blockers: rights not verified; licence type missing; source URL missing (non-originals); hosting method missing; files missing (self-hosted) or embed URL missing / not authorized / not HTTPS on an allow-listed host (iframe); Flash compatibility not Compatible or Partial; last launch check failed; thumbnail rights not confirmed (non-originals with a thumbnail); English title missing.

Note: uploading a new package to a game that is already published unpublishes it first, so the new files are not public until the game is checked, previewed and published again. The launch status becomes `untested`.

### Flash (Ruffle) compatibility

Flash games run through self-hosted Ruffle, which does not support every SWF. Set **Flash compatibility** in the Engine section after testing the game in Preview:

| Status | Meaning | Public? |
|---|---|---|
| Untested | Default after upload | No |
| Compatible | Plays correctly | Yes |
| Partially compatible | Playable with minor issues | Yes |
| Unsupported | Uses features Ruffle does not support | No |
| Broken | Does not run | No |

Only Compatible and Partial pass the publish gate and `scopePublic()`. Never upload a SWF without documented redistribution rights; the platform does not download Flash games automatically.

### Bulk actions

Select rows, choose an action and apply (max 200 games):

| Action | Permission | Notes |
|---|---|---|
| Publish / Unpublish | `games.publish` | Each game goes through the publish gate; failures are listed with their blockers |
| Feature / Unfeature | `games.manage` | Sets `is_featured` (used by the homepage hero) |
| Run launch check | `games.manage` | Same as the single launch check |
| Add to category | `games.manage` | Adds a non-primary category |
| Move to trash | `games.manage` | Archives and soft-deletes |

### Trash

"Move to trash" sets status `archived` and soft-deletes the game (hidden everywhere). In the Trash view, "Restore as draft" brings it back as a `draft` (rights status is unchanged). "Delete permanently" (requires `games.publish`) removes the game and its play history; uploaded files under `public/game-files/` stay on disk until removed manually.

## Categories and tags

Categories form a two-level tree (a parent must be a top-level category). Fields: translated name, description, SEO title/description, slug, icon, color, active, show in menu, show on home. Drag and drop rows to reorder; each level of the tree is saved separately (`POST /admin/categories/reorder`). A category with subcategories cannot be deleted; deleting a category keeps its games.

Tags have a translated name and slug. Games get tags from the comma-separated tag field on the game form (new tags are created automatically with an English name).

## Imports and providers

See `docs/GAME_IMPORT_GUIDE.md`. In short: providers hold the embed host allow-list, licence defaults and the adapter; imports preview a CSV/JSON file or provider feed, then create **draft games with unverified rights** that still go through the workflow above.

## Reports

Player reports (reasons: not loading, crashes, controls, inappropriate, copyright, other) with an optional message. Filter by status (open, resolved, dismissed, all) and reason; the top five most-reported games are shown. Mark a report resolved, dismissed or reopen it. Copyright reports must follow the takedown procedure in `docs/OPERATIONS.md`.

## Homepage

The homepage is a list of sections. Drag and drop to reorder (saved immediately), toggle visibility, edit or remove sections, or add new ones (added at the bottom).

Section types: hero (featured carousel), trending, most played, new, editors' picks, multiplayer, mobile friendly, category (pick a category), Flash, originals, continue playing, recently played, recommended, random, category grid, SEO text (translated body), ad (pick a placement). Game-list types accept a limit (1-48). Sections with no games are skipped automatically.

## Pages and blog

One editor for all content types: `page`, `legal`, `blog`, `news`, `faq`, `landing`. Blog and news appear under `/{locale}/blog/{slug}`; the rest under `/{locale}/p/{slug}`. Fields: type, slug (generated from the English title if empty; unique per type), status (draft or published), optional publish date (a future date schedules it), translated title, excerpt, body (Markdown), SEO title and description. Legal pages use tokens such as `:brand`, `:operator`, `:contact_email`, `:jurisdiction` filled from the environment.

## Menus

Three menus: Header, Footer - main links, Footer - legal links. Each item has a translated label, a URL (a relative path such as `p/about`, prefixed with the visitor's locale when rendered, or an absolute `http(s)://` URL), sort order and enabled flag.

## Design

Theme tokens: primary, secondary, accent and dark background colors; display and body font (Outfit, Inter, system UI); corner radius; card style; density; default color mode. Saving creates a new **theme version** (optionally activated immediately). The version list shows every saved version; **Activate** switches the live site to any earlier version, which is how you roll back a design change.

Branding: upload a logo (max 2000x1000) and favicon (max 1024x1024) as PNG, JPEG or WebP (max 1 MB). SVG is not accepted because SVG files can contain scripts. Uploaded branding files are stored as uploaded (not re-encoded).

## Advertising

- **Placements** (`home_top`, `home_mid`, `category_top`, `game_below`, `sidebar`) are disabled by default. Enable one to start serving. No placement exists inside or on top of the game player.
- **Campaigns**: name, type, placement, priority (higher wins), optional start and end dates, active flag.
  - Direct banner: PNG/JPEG/WebP image up to 1 MB, alt text and an HTTPS target URL.
  - Sponsored game: links to a game page.
  - Google AdSense: the numeric `data-ad-slot` id.
- Statistics are served impressions (counted when a house or sponsored ad is rendered into a page; AdSense campaigns are not shown without `ADSENSE_CLIENT` and their impressions are counted by Google, not here) and clicks through `/ad/{id}/click`. The platform never simulates impressions or clicks. Revenue is not imported from any network and stays 0.
- **AdSense**: set `ADSENSE_CLIENT` only after your AdSense account is approved. AdSense code is then loaded only for visitors who chose "Accept all" in the cookie banner. The built-in banner is not a certified consent management platform; serving AdSense to visitors in the EEA, UK or Switzerland requires a Google-certified CMP (see `docs/SECURITY.md`).

## SEO

- Overview: number of public games, active categories and published pages, and per-locale counts of public games missing an SEO description or description, categories missing a description, and pages missing an SEO description. Shows whether GA4 and Search Console verification are configured.
- **Redirects**: from a path on this site (no query string or fragment; normalised to `/a/b`) to a relative path or an `https://` URL, with status 301 or 302. Redirects apply only when the path would otherwise return 404, so they cannot override existing pages. Hits are counted.

## Settings

- Announcement banner (translated, max 300 chars; empty to hide).
- Registration open/closed (closed returns 403 on the registration page).
- Social links (X/Twitter, Discord, YouTube; HTTPS URLs on those domains only).
- Read-only environment info: brand, default and available languages, GA4 id. These change through environment variables and a redeploy.

## Translations

Edits the UI strings shipped in `lang/ka.json`, `lang/tr.json`, `lang/ru.json`. Keys are the English source strings, so English itself is not edited here. Search keys or values, filter to missing translations, and save a page at a time; clearing a field removes the translation (the English text is shown). Only existing keys can be edited.

Edits are saved as overrides in `storage/app/lang-overrides/{locale}.json`, not in `lang/`. Storage is shared between releases, so edits survive deploys and are included in backups; the shipped files in git stay unchanged. To make an edit permanent in the repository, copy it into `lang/{locale}.json`.

## Analytics

First-party statistics for 7, 30 or 90 days: daily plays, unique visitors per day (visitor hashes rotate daily, so totals are "visitor-days"), failed loads, average play time, registrations, searches; top devices, locales, countries (only when a CDN country header is present), referrer hosts; top games with average time and failures; top searches and zero-result searches; ad totals. If GA4 is configured it is noted here, but GA4 data lives in Google Analytics.

## Audit log

Every sensitive admin action: game create/update/publish/unpublish/rights/package/check/delete/restore/bulk, imports, providers, categories, tags, pages, menus, homepage, theme and branding, ads, redirects, settings, translations, report status, user suspension and role changes. Each entry stores the acting user, action, subject, JSON details and the acting user's IP address. Filter by action prefix, user id or subject type. Entries are not editable from the panel.

## Users

- List with search (nickname or email), role filter, and suspended/staff filters.
- User page: counts of plays, favorites, ratings, scores; recent plays; audit entries about the user.
- **Suspend** (with a required reason) and **Unsuspend**: `users.moderate`. A suspended user is logged out on their next request, cannot log in, loses all admin permissions and is hidden from leaderboards.
- **Change role**: `users.manage`. You cannot change your own account, moderators cannot act on staff accounts, and only a super admin can grant or remove super admin.
