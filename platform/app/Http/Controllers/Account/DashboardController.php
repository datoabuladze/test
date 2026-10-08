<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Game;
use App\Models\UserAchievement;
use App\Services\Gamification;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $recentIds = $user->plays()->latest('created_at')->limit(60)->pluck('game_id')->unique()->take(12)->values()->all();
        $recent = Game::query()->public()->forCard()->whereIn('id', $recentIds)->get()->sortBy(fn ($g) => array_search($g->id, $recentIds))->values();

        return view('account.dashboard', [
            'user' => $user,
            'recent' => $recent,
            'favorites' => $user->favorites()->public()->forCard()->limit(12)->get(),
            'streak' => app(Gamification::class)->streak($user),
            'seo' => Seo::make(__('My profile'))->noindex(),
        ]);
    }

    public function favorites(Request $request): View
    {
        return view('account.favorites', [
            'games' => $request->user()->favorites()->public()->forCard()->paginate(36),
            'seo' => Seo::make(__('Favorites'))->noindex(),
        ]);
    }

    public function history(Request $request): View
    {
        $plays = $request->user()->plays()->with(['game' => fn ($q) => $q->forCard()])->latest('created_at')->paginate(40);

        return view('account.history', ['plays' => $plays, 'seo' => Seo::make(__('Play history'))->noindex()]);
    }

    public function achievements(Request $request, Gamification $gamification): View
    {
        $user = $request->user();
        $achievements = Achievement::query()->where('is_active', true)->orderBy('period')->orderBy('threshold')->get();
        $unlocked = UserAchievement::query()->where('user_id', $user->id)->get()
            ->groupBy('achievement_id');

        $rows = $achievements->map(function (Achievement $a) use ($user, $unlocked, $gamification) {
            $mine = $unlocked->get($a->id, collect());
            $done = $mine->contains('period_key', $a->periodKey());

            return [
                'achievement' => $a,
                'done' => $done,
                'times' => $mine->count(),
                'progress' => $done ? $a->threshold : min($a->threshold, $gamification->progress($user, $a)),
            ];
        });

        return view('account.achievements', ['rows' => $rows, 'seo' => Seo::make(__('Achievements'))->noindex()]);
    }
}
