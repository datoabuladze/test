<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Game;
use App\Services\SearchService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->where('is_active', true)->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount(['games' => fn ($g) => $g->public()])])
            ->withCount(['games' => fn ($g) => $g->public()])
            ->orderBy('sort_order')->get();

        return view('categories.index', [
            'categories' => $categories,
            'seo' => Seo::make(__('Game categories'), __('Find your next favorite game by genre: action, puzzle, racing, sports, strategy and many more.')),
        ]);
    }

    public function show(Request $request, Category $category, SearchService $search): View
    {
        abort_unless($category->is_active, 404);
        $sort = in_array($request->query('sort'), GameController::SORTS, true) ? $request->query('sort') : 'popular';

        $query = Game::query()->public()->forCard()->inCategory($category);
        $search->applyFilters($query, $request->only(['device', 'multiplayer', 'difficulty']));
        match ($sort) {
            'new' => $query->orderByDesc('published_at'),
            'rating' => $query->orderByDesc('rating_avg'),
            'trending' => $query->orderByDesc('trending_score'),
            'az' => $query->orderBy('slug'),
            default => $query->orderByDesc('popularity_score')->orderByDesc('play_count'),
        };
        $games = $query->orderBy('id')->paginate(config('platform.per_page'))->withQueryString();
        abort_if($games->currentPage() > 1 && $games->isEmpty(), 404);

        $category->load(['children' => fn ($q) => $q->where('is_active', true), 'parent']);
        $name = $category->tr('name');
        $seo = Seo::make(
            $category->tr('seo_title') ?: __(':category games – play free online', ['category' => $name]),
            $category->tr('seo_description') ?: ($category->tr('description') ?: __('Play the best free :category games online. No downloads needed.', ['category' => $name])),
        );
        if ($request->hasAny(['sort', 'device', 'multiplayer', 'difficulty'])) {
            $seo->canonical($category->url());
        }
        if ($games->isEmpty()) {
            $seo->noindex(); // thin page: don't ask search engines to index an empty listing
        }
        $seo->jsonLd([
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $category->url(),
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $games->getCollection()->values()->map(fn (Game $g, $i) => [
                    '@type' => 'ListItem', 'position' => $i + 1, 'url' => $g->url(), 'name' => $g->tr('title'),
                ])->all(),
            ],
        ]);

        return view('categories.show', compact('category', 'games', 'sort', 'seo'));
    }
}
