<?php

namespace App\Http\Controllers;

use App\Enums\GameEngine;
use App\Models\Game;
use App\Models\Rating;
use App\Services\GameCatalog;
use App\Services\SearchService;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public const SORTS = ['popular', 'trending', 'new', 'rating', 'az'];

    public function index(Request $request, SearchService $search): View
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'popular';
        $query = Game::query()->public()->forCard();
        $search->applyFilters($query, $request->only(['device', 'multiplayer', 'engine', 'difficulty', 'provider']));
        $this->applySort($query, $sort);

        $games = $query->paginate(config('platform.per_page'))->withQueryString();
        abort_if($games->currentPage() > 1 && $games->isEmpty(), 404);

        $seo = Seo::make(__('All games'), __('Browse every free online game on :brand, sorted the way you like.', ['brand' => config('platform.brand')]));
        if ($request->hasAny(['device', 'multiplayer', 'engine', 'difficulty', 'provider', 'sort'])) {
            // Filtered/sorted variants are useful to visitors but duplicate content for crawlers.
            $seo->canonical(route('games.index'));
        }

        return view('games.index', compact('games', 'sort', 'seo'));
    }

    public function show(Request $request, Game $game, GameCatalog $catalog): View
    {
        abort_unless($game->isPublic() || $request->user()?->hasPermission('games.manage'), 404);

        $game->load(['categories', 'tags', 'provider']);
        $user = $request->user();
        $userRating = $user ? Rating::query()->where('user_id', $user->id)->where('game_id', $game->id)->value('stars') : null;
        $isFavorite = $user ? $user->favorites()->whereKey($game->id)->exists() : false;
        $similar = $catalog->similar($game, 12);

        $title = $game->tr('seo_title') ?: __(':title – Play free online', ['title' => $game->tr('title')]);
        $description = $game->tr('seo_description') ?: ($game->tr('short_description') ?: $game->tr('description'));
        $seo = Seo::make($title, $description)->type('website')->image($game->thumbnailUrl());
        if (! $game->isPublic()) {
            $seo->noindex();
        }
        $seo->jsonLd(array_filter([
            '@type' => 'VideoGame',
            'name' => $game->tr('title'),
            'description' => $description,
            'url' => $game->url(),
            'image' => $game->thumbnailUrl() ? url($game->thumbnailUrl()) : null,
            'genre' => $game->categories->map(fn ($c) => $c->tr('name', 'en'))->values()->all(),
            'gamePlatform' => ['Web browser'],
            'applicationCategory' => 'Game',
            'operatingSystem' => 'Any',
            'author' => $game->developer ? ['@type' => 'Organization', 'name' => $game->developer] : null,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock'],
            'aggregateRating' => $game->rating_count >= 3 ? [
                '@type' => 'AggregateRating', 'ratingValue' => round($game->rating_avg, 1), 'ratingCount' => $game->rating_count,
                'bestRating' => 5, 'worstRating' => 1,
            ] : null,
        ]));

        return view('games.show', [
            'game' => $game,
            'similar' => $similar,
            'userRating' => $userRating,
            'isFavorite' => $isFavorite,
            'seo' => $seo,
            'frameUrl' => $this->frameUrl($game),
        ]);
    }

    public function random(GameCatalog $catalog): RedirectResponse
    {
        $game = $catalog->random();

        return $game ? redirect($game->url()) : redirect()->route('home');
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'trending' => $query->orderByDesc('trending_score')->orderByDesc('play_count'),
            'new' => $query->orderByDesc('published_at'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'az' => $query->orderBy('slug'),
            default => $query->orderByDesc('popularity_score')->orderByDesc('play_count'),
        };
        $query->orderBy('id');
    }

    private function frameUrl(Game $game): string
    {
        if ($game->engine === GameEngine::Iframe) {
            return (string) $game->embed_url;
        }
        $origin = config('platform.games_origin');
        $path = route('games.frame', $game, false);

        return $origin ? $origin.$path : url($path);
    }
}
