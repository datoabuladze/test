<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAchievement;
use App\Support\Seo;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(string $nickname): View
    {
        $user = User::query()->where('nickname', $nickname)->whereNull('suspended_at')->firstOrFail();
        $isOwner = auth()->id() === $user->id;
        abort_unless($user->profile_public || $isOwner, 404);

        $favorites = $user->favorites()->public()->forCard()->limit(18)->get();
        $achievements = UserAchievement::query()->where('user_id', $user->id)->with('achievement')
            ->latest('unlocked_at')->limit(24)->get();
        $stats = [
            'plays' => $user->plays()->count(),
            'games' => $user->plays()->distinct()->count('game_id'),
            'favorites' => $user->favorites()->count(),
            'achievements' => UserAchievement::query()->where('user_id', $user->id)->count(),
        ];

        return view('profiles.show', [
            'profile' => $user, 'favorites' => $favorites, 'achievements' => $achievements, 'stats' => $stats,
            'seo' => Seo::make(__(':name’s profile', ['name' => $user->nickname]))->noindex(),
        ]);
    }
}
