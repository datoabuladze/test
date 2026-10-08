<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdStatDaily;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\SearchQuery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * First-party, privacy-preserving analytics computed from the platform's own records.
 * Visitor hashes rotate daily, so "visitor-days" counts unique visitors per day, summed.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $from = now()->subDays($days - 1)->startOfDay();
        $plays = GamePlay::query()->where('created_at', '>=', $from);

        $daily = (clone $plays)->selectRaw('date(created_at) as d, count(*) as plays, count(distinct visitor_hash) as visitors, sum(case when load_status = ? then 1 else 0 end) as failed', ['failed'])
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');
        $series = collect(range(0, $days - 1))->map(function ($i) use ($from, $daily) {
            $d = $from->copy()->addDays($i)->toDateString();

            return ['date' => $d, 'plays' => (int) ($daily[$d]->plays ?? 0), 'visitors' => (int) ($daily[$d]->visitors ?? 0), 'failed' => (int) ($daily[$d]->failed ?? 0)];
        });

        $totals = [
            'plays' => $series->sum('plays'),
            'visitor_days' => $series->sum('visitors'),
            'failed' => $series->sum('failed'),
            'avg_seconds' => (int) round((float) (clone $plays)->where('duration_seconds', '>', 0)->avg('duration_seconds')),
            'registrations' => User::query()->where('created_at', '>=', $from)->count(),
            'searches' => SearchQuery::query()->where('created_at', '>=', $from)->count(),
        ];

        $group = fn (string $col) => (clone $plays)->selectRaw("coalesce($col, 'unknown') as k, count(*) as c")->groupBy('k')->orderByDesc('c')->limit(10)->pluck('c', 'k');

        $topGames = (clone $plays)->selectRaw('game_id, count(*) as plays, avg(duration_seconds) as avg_s, sum(case when load_status = ? then 1 else 0 end) as failed', ['failed'])
            ->groupBy('game_id')->orderByDesc('plays')->limit(15)->get();
        $gameTitles = Game::withTrashed()->whereIn('id', $topGames->pluck('game_id'))->get(['id', 'slug', 'title'])->keyBy('id');

        $ads = AdStatDaily::query()->where('date', '>=', $from->toDateString())
            ->selectRaw('sum(impressions) as impressions, sum(clicks) as clicks, sum(revenue) as revenue')->first();

        return view('admin.analytics.index', [
            'days' => $days,
            'series' => $series,
            'maxPlays' => max(1, $series->max('plays')),
            'totals' => $totals,
            'devices' => $group('device'),
            'locales' => $group('locale'),
            'countries' => $group('country'),
            'referrers' => $group('referrer_host'),
            'topGames' => $topGames,
            'gameTitles' => $gameTitles,
            'topSearches' => SearchQuery::query()->where('created_at', '>=', $from)->selectRaw('query, count(*) as c, max(results_count) as results')->groupBy('query')->orderByDesc('c')->limit(15)->get(),
            'zeroSearches' => SearchQuery::query()->where('created_at', '>=', $from)->where('results_count', 0)->selectRaw('query, count(*) as c')->groupBy('query')->orderByDesc('c')->limit(15)->get(),
            'ads' => $ads,
            'ga4' => config('platform.analytics.ga4_measurement_id'),
        ]);
    }
}
