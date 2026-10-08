<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameStatDaily;
use App\Services\GameCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls raw plays into game_stats_daily and recomputes the denormalized counters and
 * ranking scores used by "Popular" and "Trending". Safe to re-run (upserts).
 */
class AggregateStats extends Command
{
    protected $signature = 'stats:aggregate {--days=2 : How many recent days to (re)aggregate}';

    protected $description = 'Aggregate daily game stats and recompute popularity and trending scores';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $this->aggregateDay($day);
        }

        $this->recomputeCounters();
        $this->recomputeScores();
        GameCatalog::flush();
        $this->info("Aggregated $days day(s) and refreshed counters and scores.");

        return self::SUCCESS;
    }

    private function aggregateDay(Carbon $day): void
    {
        $rows = DB::table('game_plays')
            ->whereBetween('created_at', [$day, $day->copy()->endOfDay()])
            ->selectRaw("game_id, count(*) as plays,
                sum(case when load_status = 'loaded' then 1 else 0 end) as loads,
                sum(case when load_status = 'failed' then 1 else 0 end) as failures,
                count(distinct visitor_hash) as unique_visitors,
                sum(duration_seconds) as seconds_played")
            ->groupBy('game_id')->get();

        $date = $day->toDateString();
        foreach ($rows->chunk(500) as $chunk) {
            GameStatDaily::query()->upsert($chunk->map(fn ($r) => [
                'game_id' => $r->game_id, 'date' => $date, 'plays' => (int) $r->plays, 'loads' => (int) $r->loads,
                'failures' => (int) $r->failures, 'unique_visitors' => (int) $r->unique_visitors, 'seconds_played' => (int) $r->seconds_played,
            ])->all(), ['game_id', 'date'], ['plays', 'loads', 'failures', 'unique_visitors', 'seconds_played']);
        }
    }

    private function recomputeCounters(): void
    {
        $plays = DB::table('game_plays')->selectRaw('game_id, count(*) as c')->groupBy('game_id')->pluck('c', 'game_id');
        $favs = DB::table('favorites')->selectRaw('game_id, count(*) as c')->groupBy('game_id')->pluck('c', 'game_id');
        $ratings = DB::table('ratings')->selectRaw('game_id, count(*) as c, avg(stars) as a')->groupBy('game_id')->get()->keyBy('game_id');

        Game::withTrashed()->select(['id'])->chunkById(500, function ($games) use ($plays, $favs, $ratings) {
            foreach ($games as $g) {
                DB::table('games')->where('id', $g->id)->update([
                    'play_count' => (int) ($plays[$g->id] ?? 0),
                    'favorites_count' => (int) ($favs[$g->id] ?? 0),
                    'rating_count' => (int) ($ratings[$g->id]->c ?? 0),
                    'rating_avg' => round((float) ($ratings[$g->id]->a ?? 0), 2),
                ]);
            }
        });
    }

    /**
     * popularity: long-term, log-scaled plays + favorites + Bayesian rating.
     * trending: plays in the last 3 days weighted against the previous 11 (velocity), so
     * new games with momentum rise even without a long history.
     */
    private function recomputeScores(): void
    {
        $since3 = now()->subDays(3)->toDateString();
        $since14 = now()->subDays(14)->toDateString();
        $recent = DB::table('game_stats_daily')->where('date', '>=', $since3)->selectRaw('game_id, sum(plays) as p, sum(seconds_played) as s')->groupBy('game_id')->get()->keyBy('game_id');
        $older = DB::table('game_stats_daily')->whereBetween('date', [$since14, $since3])->selectRaw('game_id, sum(plays) as p')->groupBy('game_id')->pluck('p', 'game_id');
        $globalAvg = (float) DB::table('ratings')->avg('stars') ?: 3.5;

        DB::table('games')->select(['id', 'play_count', 'favorites_count', 'rating_count', 'rating_avg'])->orderBy('id')->chunk(500, function ($games) use ($recent, $older, $globalAvg) {
            foreach ($games as $g) {
                $bayes = ($g->rating_count * $g->rating_avg + 5 * $globalAvg) / ($g->rating_count + 5);
                $popularity = log10(1 + $g->play_count) * 10 + log10(1 + $g->favorites_count) * 6 + ($bayes - 3) * 4;

                $r = $recent[$g->id] ?? null;
                $recentPlays = (float) ($r->p ?? 0);
                $avgSeconds = $recentPlays > 0 ? ($r->s / $recentPlays) : 0;
                $baseline = ((float) ($older[$g->id] ?? 0)) / 11 * 3; // expected plays in 3 days
                $velocity = ($recentPlays + 1) / ($baseline + 3);
                $trending = log10(1 + $recentPlays) * 10 * min(4, $velocity) + min(5, $avgSeconds / 60);

                DB::table('games')->where('id', $g->id)->update([
                    'popularity_score' => round($popularity, 4),
                    'trending_score' => round($trending, 4),
                ]);
            }
        });
    }
}
