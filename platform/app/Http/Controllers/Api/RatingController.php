<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Rating;
use App\Services\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function store(Request $request, Game $game, Gamification $gamification): JsonResponse
    {
        abort_unless($game->isPublic(), 404);
        $data = $request->validate(['stars' => ['required', 'integer', 'between:1,5']]);
        $user = $request->user();

        DB::transaction(function () use ($user, $game, $data) {
            $exists = Rating::query()->where('user_id', $user->id)->where('game_id', $game->id)->exists();
            if ($exists) {
                Rating::query()->where('user_id', $user->id)->where('game_id', $game->id)
                    ->update(['stars' => $data['stars'], 'updated_at' => now()]);
            } else {
                Rating::query()->insert(['user_id' => $user->id, 'game_id' => $game->id, 'stars' => $data['stars'],
                    'created_at' => now(), 'updated_at' => now()]);
            }
            $agg = Rating::query()->where('game_id', $game->id)->selectRaw('COUNT(*) as c, AVG(stars) as a')->first();
            $game->forceFill(['rating_count' => (int) $agg->c, 'rating_avg' => round((float) $agg->a, 2)])->saveQuietly();
        });
        $gamification->evaluate($user);

        return response()->json([
            'rating_avg' => (float) $game->rating_avg,
            'rating_count' => (int) $game->rating_count,
            'message' => __('Thanks for rating!'),
        ]);
    }
}
