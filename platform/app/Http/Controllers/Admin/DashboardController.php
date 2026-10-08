<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameReport;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $since = now()->subDay();

        $stats = [
            'public' => Game::query()->public()->count(),
            'total' => Game::query()->count(),
            'drafts' => Game::query()->where('status', GameStatus::Draft)->count(),
            'unverified' => Game::query()->where('rights_status', RightsStatus::Unverified)->count(),
            'failed' => Game::query()->where('launch_status', 'failed')->count(),
            'untested' => Game::query()->where('launch_status', 'untested')->count(),
            'plays_24h' => GamePlay::query()->where('created_at', '>=', $since)->count(),
            'failed_loads_24h' => GamePlay::query()->where('created_at', '>=', $since)->where('load_status', 'failed')->count(),
            'users' => User::query()->count(),
            'users_24h' => User::query()->where('created_at', '>=', $since)->count(),
            'open_reports' => GameReport::query()->where('status', 'open')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'topGames' => Game::query()->select(['id', 'slug', 'title', 'play_count', 'status'])
                ->withCount(['plays as plays_24h' => fn ($q) => $q->where('created_at', '>=', $since)])
                ->orderByDesc('plays_24h')->limit(8)->get(),
            'needsAttention' => Game::query()->select(['id', 'slug', 'title', 'status', 'rights_status', 'launch_status', 'last_check_message'])
                ->where(fn ($q) => $q->where('launch_status', 'failed')->orWhere('rights_status', RightsStatus::Unverified->value))
                ->latest('updated_at')->limit(8)->get(),
            'reports' => GameReport::query()->with('game:id,slug,title')->where('status', 'open')->latest()->limit(6)->get(),
            'imports' => ImportBatch::query()->with('provider:id,name')->latest()->limit(5)->get(),
            'activity' => AuditLog::query()->with('user:id,nickname')->latest('created_at')->limit(10)->get(),
        ]);
    }
}
