<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public const REASONS = ['not_loading', 'crashes', 'controls', 'inappropriate', 'copyright', 'other'];

    public function store(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->isPublic(), 404);
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', self::REASONS)],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        GameReport::query()->create([
            'game_id' => $game->id,
            'user_id' => $request->user()?->id,
            'reason' => $data['reason'],
            'message' => $data['message'] ?? null,
            'ip_hash' => hash('sha256', config('app.key').$request->ip()),
        ]);

        return response()->json(['ok' => true, 'message' => __('Thanks! We will look into it.')], 201);
    }
}
