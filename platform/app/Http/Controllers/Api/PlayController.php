<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePlay;
use App\Services\Gamification;
use App\Services\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PlayController extends Controller
{
    public function start(Request $request, Game $game, Gamification $gamification): JsonResponse
    {
        abort_unless($game->isPublic() || $request->user()?->hasPermission('games.manage'), 404);

        $play = GamePlay::query()->create([
            'game_id' => $game->id,
            'user_id' => $request->user()?->id,
            'visitor_hash' => Visitor::hash($request),
            'device' => Visitor::device($request),
            'locale' => app()->getLocale(),
            'country' => Visitor::country($request),
            'referrer_host' => Visitor::referrerHost($request),
        ]);
        $game->increment('play_count');

        if ($user = $request->user()) {
            $gamification->onPlay($user, $game);
        }

        return response()->json([
            'play_id' => $play->id,
            // Signed so a client can only update its own play record.
            'status_url' => URL::temporarySignedRoute('api.plays.status', now()->addHours(6), ['play' => $play->id]),
        ]);
    }

    public function status(Request $request, GamePlay $play): JsonResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $data = $request->validate([
            'status' => ['required', 'in:loaded,failed,ended'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $updates = ['duration_seconds' => max($play->duration_seconds, (int) ($data['seconds'] ?? 0))];
        if ($data['status'] !== 'ended' && $play->load_status === 'started') {
            $updates['load_status'] = $data['status'];
        }
        $play->update($updates);

        return response()->json(['ok' => true]);
    }
}
