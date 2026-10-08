<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Renders game cards for ids stored in the visitor's browser (recently played). */
class GameListController extends Controller
{
    public function byIds(Request $request, GameCatalog $catalog): Response
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:24'],
            'ids.*' => ['integer'],
            'variant' => ['nullable', 'in:rail,grid'],
        ]);
        $games = $catalog->byIdsPreservingOrder($data['ids']);
        if ($games->isEmpty()) {
            return response('', 200);
        }
        $view = ($data['variant'] ?? 'rail') === 'grid' ? 'partials.cards-grid' : 'partials.cards-rail';

        return response()->view($view, ['games' => $games]);
    }
}
