<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Game $game, Gamification $gamification): JsonResponse
    {
        abort_unless($game->isPublic(), 404);
        $user = $request->user();
        $result = $user->favorites()->toggle([$game->id]);
        $favorited = ! empty($result['attached']);

        $game->forceFill(['favorites_count' => $game->favoritedBy()->count()])->saveQuietly();
        if ($favorited) {
            $gamification->evaluate($user);
        }

        return response()->json([
            'favorited' => $favorited,
            'message' => $favorited ? __('Added to favorites') : __('Removed from favorites'),
        ]);
    }
}
