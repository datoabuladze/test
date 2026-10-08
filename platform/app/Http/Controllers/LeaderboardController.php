<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Score;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(): View
    {
        $players = User::query()->where('profile_public', true)->whereNull('suspended_at')
            ->where('xp', '>', 0)->orderByDesc('xp')->limit(50)->get(['id', 'nickname', 'xp', 'level', 'avatar_path']);
        $games = Game::query()->public()->forCard()->where('score_mode', '!=', 'none')->orderByDesc('play_count')->get();

        return view('leaderboards.index', [
            'players' => $players,
            'games' => $games,
            'seo' => Seo::make(__('Leaderboards'), __('Top players and high scores on :brand.', ['brand' => config('platform.brand')])),
        ]);
    }

    public function show(Request $request, Game $game): View
    {
        abort_unless($game->isPublic() && $game->score_mode !== 'none', 404);
        $verifiedOnly = $request->query('board', $game->score_mode === 'verified' ? 'verified' : 'casual') === 'verified';
        $period = in_array($request->query('period'), ['all', 'month', 'week', 'day'], true) ? $request->query('period') : 'all';

        $best = Score::query()->where('game_id', $game->id)
            ->when($verifiedOnly, fn ($q) => $q->where('is_verified', true))
            ->when($period !== 'all', fn ($q) => $q->where('created_at', '>=', match ($period) {
                'day' => now()->startOfDay(), 'week' => now()->startOfWeek(), default => now()->startOfMonth(),
            }))
            ->select('user_id', DB::raw('MAX(score) as best'))
            ->groupBy('user_id')->orderByDesc('best')->limit(100)->get();

        $users = User::query()->whereIn('id', $best->pluck('user_id'))->where('profile_public', true)->whereNull('suspended_at')
            ->get(['id', 'nickname', 'level', 'avatar_path'])->keyBy('id');
        $rows = $best->filter(fn ($r) => isset($users[$r->user_id]))->values()
            ->map(fn ($r, $i) => ['rank' => $i + 1, 'user' => $users[$r->user_id], 'score' => (int) $r->best]);

        $mine = $request->user() ? Score::query()->where('game_id', $game->id)->where('user_id', $request->user()->id)
            ->when($verifiedOnly, fn ($q) => $q->where('is_verified', true))->max('score') : null;

        return view('leaderboards.show', [
            'game' => $game, 'rows' => $rows, 'verifiedOnly' => $verifiedOnly, 'period' => $period, 'mine' => $mine,
            'seo' => Seo::make(__(':game leaderboard', ['game' => $game->tr('title')]))->noindex(),
        ]);
    }
}
