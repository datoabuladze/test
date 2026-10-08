<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. Production needs one cron entry:
|   * * * * * cd /path/to/platform && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('stats:aggregate --days=2')->hourly()->withoutOverlapping();
Schedule::command('platform:prune')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('games:smoke --untested')->dailyAt('04:00')->withoutOverlapping()->runInBackground();
Schedule::command('queue:prune-failed --hours=168')->daily();
