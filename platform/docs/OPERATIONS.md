# Operations runbook

Day-to-day operation of the platform. Paths assume the production layout in `docs/DEPLOYMENT.md` (`/var/www/nebulo/current`, shared files in `/var/www/nebulo/shared`). No production deployment has been performed yet, so these procedures have not been exercised against a live system. Run artisan commands as the `nebulo` user from `/var/www/nebulo/current`.

## Scheduled jobs

One cron entry runs the Laravel scheduler every minute (`deploy/cron/nebulo`). The schedule is defined in `routes/console.php`:

| Command | When | What it does | If it fails |
|---|---|---|---|
| `stats:aggregate --days=2` | hourly (no overlap) | Rolls the last 2 days of `game_plays` into `game_stats_daily`; recomputes play, favorite and rating counters, `popularity_score` and `trending_score`; flushes the catalog cache | Trending/popular ordering goes stale; rerun manually, use `--days=N` to backfill |
| `platform:prune` | daily 03:30 | Marks expired rooms, deletes rooms expired more than a day ago, score sessions older than 1 day, raw plays older than **25 months**, search logs older than **12 months** (the retention promised in the privacy policy). Aggregates are kept | Retention promise is broken; rerun manually |
| `games:smoke --untested` | daily 04:00 (background) | Real-browser launch test of games never tested, marked `untested`, or not checked in 7 days. Sets `launch_status` to `ok` or `failed`; **failed games disappear from the catalog** | Requires Node, Playwright, Chromium (`CHROMIUM_PATH`) and `APP_URL` reachable from the server. If the browser cannot start, nothing is saved |
| `queue:prune-failed --hours=168` | daily | Deletes failed jobs older than 7 days | - |
| `deploy/scripts/backup.sh` | daily 02:15 (separate cron line) | Database dump + uploads archive, 14-day local retention | See Backups |

Check the scheduler:

```bash
php artisan schedule:list
grep -i schedule /var/www/nebulo/shared/storage/logs/laravel-*.log | tail
```

## Queue

Two supervisor workers (`deploy/supervisor/nebulo-worker.conf`) process the Redis queue. Jobs: import previews (`PreviewImportBatch`, timeout 600 s) and import runs (`RunImportBatch`, timeout 1800 s) for large files and provider feeds, plus any queued mail.

```bash
sudo supervisorctl status nebulo-worker:*
php artisan queue:failed                 # list failed jobs
php artisan queue:retry <id|all>
php artisan queue:restart                # after config changes; deploy.sh does this
```

Keep `REDIS_QUEUE_RETRY_AFTER` above 1800 (default 1900, see `docs/DEPLOYMENT.md`); otherwise a long import can be started twice.

## Monitoring

| Signal | Where |
|---|---|
| Health | `GET /up` returns 200 when the app boots (used by `deploy.sh`). Point an external uptime monitor at it and at a game page |
| Application log | `shared/storage/logs/laravel-YYYY-MM-DD.log` (`LOG_CHANNEL=daily`, `LOG_LEVEL=warning` in the production example) |
| Worker log | `shared/storage/logs/worker.log` |
| Backup log | `shared/storage/logs/backup.log` |
| nginx / PHP-FPM | `/var/log/nginx/*.log`, `journalctl -u php8.4-fpm` |
| Failed jobs | `php artisan queue:failed` |
| Game health | Admin dashboard: failed launches, untested games, failed loads in 24 h, open reports. Admin > Analytics: failed loads per game |

There is no built-in alerting or error tracker; add one (log shipping, uptime monitor) before launch.

## Handling game reports

Admin > Reports (permission `reports.manage`). Default view is open reports; the top five most-reported games are listed.

| Reason | Action |
|---|---|
| `not_loading`, `crashes` | Open the game in Admin > Games > Preview. Run a launch check and `php artisan games:smoke --game=<slug>`. If broken, unpublish (or let the smoke test mark it `failed`) and fix files/provider; then resolve |
| `controls` | Check the controls text and `input_types`/devices; fix the game page; resolve |
| `inappropriate` | Review content and age rating (`min_age`); unpublish if needed; resolve or dismiss |
| `copyright` | Follow the takedown procedure below |
| `other` | Read the message; resolve or dismiss |

Status changes are recorded in the audit log (`report.resolved`, `report.dismissed`, `report.open`).

## Copyright / DMCA takedown procedure

Notices arrive at `CONTACT_EMAIL` (published at `/{locale}/p/takedown`) or as reports with reason `copyright`.

1. **Unpublish first.** Admin > Games > the game > Unpublish (or Rights review > Rejected, which also unpublishes). Do this as soon as the notice is plausible; do not wait for the full review. The game disappears from listings, search, sitemaps and its page returns 404 for visitors.
2. **Record it.** Unpublishing and rights decisions are written to the audit log automatically. Add a rights-review note with the date, sender and a summary of the claim (the note is appended to the game's licence notes with your nickname). Keep the original email.
3. **Respond.** Acknowledge receipt to the sender (the takedown page says this usually happens within two business days). For provider-supplied games, forward the notice to the provider.
4. **Decide.**
   - **Restore** only with documented proof that the use is licensed (provider agreement, licence, or a valid counter-notice): set rights to Verified again and publish.
   - **Delete** otherwise: move to trash, then delete permanently (`games.publish`). Remove the uploaded files in `shared/game-files/<id>-*/` manually, since permanent delete leaves them on disk.
5. Mark related reports resolved.

## Adding games

- Original games: see "Adding an ORIGINAL game" in `docs/GAME_IMPORT_GUIDE.md` (commit to the repository, deploy, then `php artisan db:seed --class=OriginalGamesSeeder --force` and `php artisan games:smoke --game=<key>`).
- Third-party games: Admin > Games or Admin > Imports, following the rights-first workflow in `docs/ADMIN_GUIDE.md`. Never publish without documented rights.
- After bulk changes, `php artisan games:smoke --untested` gives every new game a real launch test.

## Rotating secrets

| Secret | Procedure | Side effects |
|---|---|---|
| `APP_KEY` | `php artisan key:generate --show`, put the value in `shared/.env`, `php artisan config:cache`, reload PHP-FPM, `php artisan queue:restart` | Everyone is logged out; signed URLs (play status, previews, verification links) and encrypted cookies (room seats) become invalid; if `VISITOR_HASH_SALT` is empty, visitor hashes change |
| `VISITOR_HASH_SALT` | Set a new random value, `config:cache`, reload FPM | Same-day visitor hashes stop matching (unique counts for that day are split) |
| `DB_PASSWORD` | Change in the database, then `.env`, `config:cache`, reload FPM, restart workers. Update anything else using the credentials (backups read `.env` directly) | Brief errors between the two changes |
| `REDIS_PASSWORD` | Same pattern | Sessions may be lost if Redis is restarted |
| `MAIL_PASSWORD` | Update `.env`, `config:cache`, restart workers | - |
| Admin passwords | Users change their own in Account > Settings (logs out other devices); or `php artisan tinker` to set a new password for a locked-out admin | - |
| Deploy key | Replace the read-only deploy key in the git host and on the server | - |

After any `.env` change: `php artisan config:cache && sudo systemctl reload php8.4-fpm && php artisan queue:restart`.

## Backups and restores

`deploy/scripts/backup.sh` (nightly) writes to `/var/backups/nebulo` (`NEBULO_BACKUP_DIR`):

- `db-<stamp>.dump` (PostgreSQL custom format) or `db-<stamp>.sql.gz` (MariaDB/MySQL);
- `files-<stamp>.tar.gz` with `storage/app/public` (thumbnails, avatars, ads, branding), `storage/app/lang-overrides` (admin translation edits) and `game-files/` (uploaded packages).

Copies older than 14 days are deleted. **Copy backups off-site**; the script does not. Original games and code are in git, not in the backup.

Restore (put the site in maintenance mode first):

```bash
cd /var/www/nebulo/current
php artisan down

# PostgreSQL
PGPASSWORD=... pg_restore --clean --if-exists --no-owner -h 127.0.0.1 -U nebulo -d nebulo /var/backups/nebulo/db-<stamp>.dump

# MariaDB / MySQL
gunzip -c /var/backups/nebulo/db-<stamp>.sql.gz | mysql -h 127.0.0.1 -u nebulo -p nebulo

# Files
tar -xzf /var/backups/nebulo/files-<stamp>.tar.gz -C /var/www/nebulo/shared

php artisan cache:clear
php artisan up
```

Test a restore on a separate machine regularly.

## Rollback

```bash
sudo -u nebulo /var/www/nebulo/current/deploy/scripts/rollback.sh
```

Switches `current` to the previous release, reloads FPM, restarts workers. Database migrations are **not** rolled back. If the bad release changed the schema destructively, restore the database from the last backup taken before the deploy, or run `php artisan migrate:rollback --step=N` from the bad release directory if its migrations have working `down()` methods (check first).

## Cache

```bash
php artisan cache:clear        # everything in the cache store (catalog, menus, settings, theme, sitemaps, redirects)
```

Targeted: the public catalog is versioned. `GameCatalog::flush()` increments `catalog.version` and forgets `home.sections`; it runs automatically on publish/unpublish, game, category and provider edits, imports, launch checks and every hourly `stats:aggregate`. To force it by hand:

```bash
php artisan tinker --execute='App\Services\GameCatalog::flush();'
```

Catalog entries expire after 5 minutes anyway. Sitemaps are cached for 1 hour (`sitemap.index`, `sitemap.<locale>.<type>`); settings, theme, redirects and menus are cleared when changed in the admin.

After a deploy, `config:cache`, `route:cache`, `view:cache` and `event:cache` are rebuilt by `deploy.sh`. To clear them manually: `php artisan optimize:clear`.

## Maintenance mode

```bash
php artisan down --retry=60 --refresh=15
php artisan down --secret="<random-token>"   # staff can bypass via https://www.example.com/<random-token>
php artisan up
```

`deploy.sh` uses `down --retry=15 --refresh=15` during migrations. The maintenance flag is stored in `storage/framework` (shared), so it applies to all releases. Static files served directly by nginx (game files, `/build`, `/storage`) are not affected.

## Useful one-off commands

```bash
php artisan games:smoke --game=<slug> --dry-run    # test without saving
php artisan stats:aggregate --days=30              # backfill aggregates
php artisan db:seed --class=OriginalGamesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force  # with ADMIN_EMAIL / ADMIN_PASSWORD set for this command
```
