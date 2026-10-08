<?php

namespace App\Http\Controllers;

use App\Services\GameCatalog;
use App\Services\SearchService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search, GameCatalog $catalog): View
    {
        $q = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $filters = $request->only(['category', 'device', 'multiplayer', 'engine', 'difficulty', 'provider']);
        $result = $search->search($q, $page, config('platform.per_page'), $filters);

        if ($page === 1 && SearchService::normalize($q) !== '') {
            $search->log($q, $result['games']->total());
        }

        return view('search.index', [
            'q' => $q,
            'games' => $result['games'],
            'corrected' => $result['corrected'],
            'trending' => $search->trendingQueries(),
            'fallback' => $result['games']->total() === 0 ? $catalog->trending(12) : collect(),
            'seo' => Seo::make($q !== '' ? __('Search results for “:q”', ['q' => $q]) : __('Search games'))->noindex(),
        ]);
    }

    public function suggest(Request $request, SearchService $search): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json($search->suggest($q))
            ->header('Cache-Control', 'public, max-age=60');
    }
}
