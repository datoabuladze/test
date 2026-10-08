<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function session(Request $request, Game $game, ScoreService $scores): JsonResponse
    {
        abort_unless($game->isPublic() && $game->score_mode !== 'none', 404);
        $session = $scores->startSession($request->user(), $game);

        return response()->json(['token' => $session->token, 'seed' => $session->seed]);
    }

    public function store(Request $request, Game $game, ScoreService $scores): JsonResponse
    {
        abort_unless($game->isPublic() && $game->score_mode !== 'none', 404);
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'score' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
            'evidence' => ['nullable', 'array'],
            'evidence.moves' => ['nullable', 'string', 'max:200000'],
        ]);

        $score = DB::transaction(fn () => $scores->submit(
            $request->user(), $game, $data['token'], (int) $data['score'], (int) $data['duration_ms'], $data['evidence'] ?? null,
        ));

        return response()->json([
            'id' => $score->id,
            'verified' => $score->is_verified,
            'message' => $score->is_verified ? __('Verified score saved!') : __('Score saved!'),
        ], 201);
    }
}
