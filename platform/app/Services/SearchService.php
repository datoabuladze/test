<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Game;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Database-agnostic game search.
 *
 * 1. Each query token must appear in the game's denormalized `search_text`
 *    (titles in every locale, tags, categories, developer, provider).
 * 2. When that finds little, tokens are corrected against a cached vocabulary
 *    using Levenshtein distance (typo tolerance) and the search is retried.
 * 3. Candidates are ranked in PHP: exact/prefix title matches first, then
 *    word matches, then popularity.
 */
class SearchService
{
    public const MAX_CANDIDATES = 400;

    public static function normalize(?string $query): string
    {
        $query = Str::lower(trim((string) $query));
        $query = preg_replace('/[^\p{L}\p{N}\s\-\']+/u', ' ', $query) ?? '';
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return trim(Str::limit($query, 80, ''));
    }

    /** @return list<string> */
    public static function tokens(string $normalized): array
    {
        return array_values(array_filter(explode(' ', str_replace('-', ' ', $normalized)), fn ($t) => mb_strlen($t) >= 1));
    }

    /**
     * @return array{games: LengthAwarePaginator, corrected: ?string}
     */
    public function search(string $query, int $page = 1, int $perPage = 36, array $filters = []): array
    {
        $normalized = self::normalize($query);
        $tokens = self::tokens($normalized);
        $corrected = null;

        $candidates = $tokens ? $this->candidates($tokens, $filters) : collect();

        if ($tokens && $candidates->count() < 3) {
            $fixed = $this->correctTokens($tokens);
            if ($fixed !== $tokens) {
                $more = $this->candidates($fixed, $filters);
                if ($more->count() > $candidates->count()) {
                    $candidates = $candidates->concat($more)->unique('id');
                    $corrected = implode(' ', $fixed);
                    $tokens = $fixed;
                }
            }
        }

        $ranked = $candidates
            ->map(fn (Game $g) => [$g, $this->score($g, $normalized, $tokens)])
            ->sortByDesc(fn ($pair) => $pair[1])
            ->map(fn ($pair) => $pair[0])
            ->values();

        $paginator = new LengthAwarePaginator(
            $ranked->forPage($page, $perPage)->values(),
            $ranked->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return ['games' => $paginator, 'corrected' => $corrected];
    }

    /** @return array{games: list<array>, categories: list<array>} */
    public function suggest(string $query, int $limit = 8): array
    {
        $normalized = self::normalize($query);
        if (mb_strlen($normalized) < 2) {
            return ['games' => [], 'categories' => []];
        }
        $result = $this->search($normalized, 1, $limit);
        $locale = app()->getLocale();

        $games = $result['games']->getCollection()->map(fn (Game $g) => [
            'title' => $g->tr('title'),
            'url' => $g->url($locale),
            'thumbnail' => $g->thumbnailUrl(),
            'color' => $g->thumbnail_color,
        ])->all();

        $categories = Cache::remember('search.categories', 600, fn () => Category::query()
            ->where('is_active', true)->get(['id', 'slug', 'name']))
            ->filter(function (Category $c) use ($normalized) {
                foreach (array_merge([$c->slug], array_values($c->name ?? [])) as $label) {
                    if (str_contains(Str::lower((string) $label), $normalized)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(4)
            ->map(fn (Category $c) => ['title' => $c->tr('name'), 'url' => $c->url($locale)])->values()->all();

        return ['games' => $games, 'categories' => $categories, 'corrected' => $result['corrected']];
    }

    public function log(string $query, int $results): void
    {
        $normalized = self::normalize($query);
        if ($normalized === '') {
            return;
        }
        SearchQuery::query()->create([
            'query' => $normalized,
            'locale' => app()->getLocale(),
            'results_count' => $results,
        ]);
    }

    /** @return list<string> popular queries with results in the last 7 days */
    public function trendingQueries(int $limit = 8): array
    {
        return Cache::remember("search.trending.$limit", 900, fn () => SearchQuery::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where('results_count', '>', 0)
            ->select('query', DB::raw('COUNT(*) as hits'))
            ->groupBy('query')->orderByDesc('hits')->limit($limit)->pluck('query')->all());
    }

    // ------------------------------------------------------------------ internals

    /** @param list<string> $tokens */
    private function candidates(array $tokens, array $filters): Collection
    {
        $q = Game::query()->public()->forCard()->addSelect(['search_text', 'is_featured']);
        foreach ($tokens as $token) {
            $q->where('search_text', 'like', '%'.$this->escapeLike($token).'%');
        }
        $this->applyFilters($q, $filters);

        return $q->orderByDesc('popularity_score')->limit(self::MAX_CANDIDATES)->get();
    }

    public function applyFilters(Builder $q, array $filters): Builder
    {
        if (! empty($filters['category'])) {
            $category = Category::query()->where('slug', $filters['category'])->first();
            if ($category) {
                $q->inCategory($category);
            }
        }
        if (! empty($filters['device']) && in_array($filters['device'], ['mobile', 'tablet', 'desktop'], true)) {
            $device = $filters['device'];
            if ($device === 'mobile') {
                $q->where('is_mobile_friendly', true);
            }
        }
        if (! empty($filters['multiplayer'])) {
            $q->where('is_multiplayer', true);
        }
        if (! empty($filters['engine'])) {
            $q->where('engine', $filters['engine']);
        }
        if (! empty($filters['difficulty']) && in_array($filters['difficulty'], ['easy', 'medium', 'hard'], true)) {
            $q->where('difficulty', $filters['difficulty']);
        }
        if (! empty($filters['provider'])) {
            $q->whereHas('provider', fn ($p) => $p->where('slug', $filters['provider']));
        }

        return $q;
    }

    /** @param list<string> $tokens */
    private function score(Game $game, string $normalized, array $tokens): float
    {
        $titles = array_map(fn ($t) => Str::lower((string) $t), array_values($game->title ?? []));
        $score = 0.0;
        foreach ($titles as $title) {
            if ($title === $normalized) {
                $score = max($score, 1000);
            } elseif (str_starts_with($title, $normalized)) {
                $score = max($score, 600);
            } elseif (str_contains($title, $normalized)) {
                $score = max($score, 400);
            }
            $words = preg_split('/\s+/u', $title) ?: [];
            $wordHits = 0;
            foreach ($tokens as $token) {
                foreach ($words as $word) {
                    if (str_starts_with($word, $token)) {
                        $wordHits++;
                        break;
                    }
                }
            }
            $score = max($score, 100 * $wordHits);
        }
        $score += 20 * count(array_filter($tokens, fn ($t) => str_contains((string) $game->search_text, $t)));
        $score += min(50, log10(1 + (float) $game->play_count) * 10);
        $score += $game->is_featured ? 5 : 0;

        return $score;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function correctTokens(array $tokens): array
    {
        $vocabulary = $this->vocabulary();

        return array_map(function (string $token) use ($vocabulary) {
            $len = mb_strlen($token);
            if ($len < 3 || isset($vocabulary[$token])) {
                return $token;
            }
            $maxDistance = $len <= 5 ? 1 : 2;
            $best = null;
            $bestDistance = PHP_INT_MAX;
            foreach ($vocabulary as $word => $weight) {
                $wordLen = mb_strlen((string) $word);
                if (abs($wordLen - $len) > $maxDistance) {
                    continue;
                }
                $d = $this->levenshtein($token, (string) $word);
                if ($d <= $maxDistance && ($d < $bestDistance || ($d === $bestDistance && $weight > $vocabulary[$best]))) {
                    $best = (string) $word;
                    $bestDistance = $d;
                }
            }

            return $best ?? $token;
        }, $tokens);
    }

    /** @return array<string, int> word => frequency, built from public games' search text */
    private function vocabulary(): array
    {
        $version = Cache::get('catalog.version', 0);

        return Cache::remember("search.vocabulary.v$version", 3600, function () {
            $words = [];
            Game::query()->public()->select(['id', 'search_text'])->chunkById(500, function ($games) use (&$words) {
                foreach ($games as $game) {
                    foreach (preg_split('/\s+/u', (string) $game->search_text) ?: [] as $w) {
                        if (mb_strlen($w) >= 3 && mb_strlen($w) <= 30) {
                            $words[$w] = ($words[$w] ?? 0) + 1;
                        }
                    }
                }
            });

            return $words;
        });
    }

    /** Multibyte-safe Levenshtein (PHP's levenshtein() counts bytes). */
    private function levenshtein(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        if (strlen($a) === mb_strlen($a) && strlen($b) === mb_strlen($b)) {
            return levenshtein($a, $b);
        }
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $prev = range(0, count($b));
        foreach ($a as $i => $ca) {
            $cur = [$i + 1];
            foreach ($b as $j => $cb) {
                $cur[] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }
            $prev = $cur;
        }

        return $prev[count($b)];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
