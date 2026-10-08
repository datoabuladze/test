<?php

namespace App\Services;

use App\Enums\GameEngine;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side queries for public game listings. Results are cached briefly;
 * cache keys include the locale-independent query only (cards translate at render).
 */
class GameCatalog
{
    public const CACHE_SECONDS = 300;

    public function base(): Builder
    {
        return Game::query()->public()->forCard();
    }

    public function trending(int $limit = 12): Collection
    {
        return $this->cached("trending.$limit", fn () => $this->base()
            ->orderByDesc('trending_score')->orderByDesc('play_count')->limit($limit)->get());
    }

    public function mostPlayed(int $limit = 12): Collection
    {
        return $this->cached("most_played.$limit", fn () => $this->base()
            ->orderByDesc('play_count')->limit($limit)->get());
    }

    public function newest(int $limit = 12): Collection
    {
        return $this->cached("new.$limit", fn () => $this->base()
            ->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get());
    }

    public function featured(int $limit = 6): Collection
    {
        return $this->cached("featured.$limit", fn () => Game::query()->public()->with('categories')
            ->where('is_featured', true)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function editorsPicks(int $limit = 12): Collection
    {
        return $this->cached("editors.$limit", fn () => $this->base()
            ->where('is_editors_pick', true)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function multiplayer(int $limit = 12): Collection
    {
        return $this->cached("multiplayer.$limit", fn () => $this->base()
            ->where('is_multiplayer', true)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function mobileFriendly(int $limit = 12): Collection
    {
        return $this->cached("mobile.$limit", fn () => $this->base()
            ->where('is_mobile_friendly', true)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function originals(int $limit = 12): Collection
    {
        return $this->cached("originals.$limit", fn () => $this->base()
            ->where('is_original', true)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function flash(int $limit = 12): Collection
    {
        return $this->cached("flash.$limit", fn () => $this->base()
            ->where('engine', GameEngine::Ruffle->value)->orderByDesc('popularity_score')->limit($limit)->get());
    }

    public function inCategory(string $slug, int $limit = 12): Collection
    {
        return $this->cached("category.$slug.$limit", function () use ($slug, $limit) {
            $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();
            if (! $category) {
                return new Collection;
            }

            return $this->base()->inCategory($category)
                ->orderByDesc('popularity_score')->limit($limit)->get();
        });
    }

    /** Games sharing categories/tags with the given game, best overlap first. */
    public function similar(Game $game, int $limit = 12): Collection
    {
        return $this->cached("similar.{$game->id}.$limit", function () use ($game, $limit) {
            $categoryIds = $game->categories()->pluck('categories.id');
            $tagIds = $game->tags()->pluck('tags.id');

            $results = $this->base()
                ->whereKeyNot($game->id)
                ->where(fn (Builder $q) => $q
                    ->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds))
                    ->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds)))
                ->withCount([
                    'categories as shared_categories' => fn ($c) => $c->whereIn('categories.id', $categoryIds),
                    'tags as shared_tags' => fn ($t) => $t->whereIn('tags.id', $tagIds),
                ])
                ->orderByDesc('popularity_score')
                ->limit(max(60, $limit * 4))->get()
                // Rank by overlap in PHP: column aliases can't be used in ORDER BY expressions on PostgreSQL.
                ->sortByDesc(fn (Game $g) => [$g->shared_categories * 2 + $g->shared_tags, $g->popularity_score])
                ->take($limit)->values();

            if ($results->count() < $limit) {
                $fill = $this->base()->whereKeyNot($game->id)->whereNotIn('id', $results->pluck('id'))
                    ->orderByDesc('popularity_score')->limit($limit - $results->count())->get();
                $results = $results->concat($fill);
            }

            return $results;
        });
    }

    /**
     * Recommendations from a set of seed games (favorites + recent plays):
     * popular games in the seeds' categories that the visitor has not played.
     *
     * @param  list<int>  $seedIds
     */
    public function recommendedFor(array $seedIds, int $limit = 12): Collection
    {
        if (! $seedIds) {
            return $this->trending($limit);
        }
        $categoryIds = \DB::table('category_game')->whereIn('game_id', $seedIds)
            ->select('category_id')->groupBy('category_id')->orderByRaw('COUNT(*) DESC')->limit(5)->pluck('category_id');

        $games = $this->base()->whereNotIn('id', $seedIds)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->orderByDesc('popularity_score')->limit($limit)->get();

        return $games->count() >= 4 ? $games : $this->trending($limit);
    }

    public function random(): ?Game
    {
        $ids = $this->cached('public_ids', fn () => Game::query()->public()->pluck('id')->all());

        return $ids ? Game::query()->public()->find($ids[array_rand($ids)]) : null;
    }

    /** @param  list<int>  $ids */
    public function byIdsPreservingOrder(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            return new Collection;
        }
        $games = $this->base()->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn ($id) => $games[$id] ?? null, $ids))));
    }

    public function publicCount(): int
    {
        return (int) $this->cached('public_count', fn () => Game::query()->public()->count());
    }

    public static function flush(): void
    {
        Cache::increment('catalog.version');
        Cache::forget('home.sections');
    }

    private function cached(string $key, \Closure $callback): mixed
    {
        $version = Cache::get('catalog.version', 0);

        return Cache::remember("catalog.v{$version}.$key", self::CACHE_SECONDS, $callback);
    }
}
