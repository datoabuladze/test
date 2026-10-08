<?php

namespace App\Http\Controllers;

use App\Enums\HomeSectionType;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Services\GameCatalog;
use App\Support\Seo;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(GameCatalog $catalog): View
    {
        $sections = Cache::remember('home.sections', 600, fn () => HomepageSection::query()
            ->where('is_enabled', true)->orderBy('sort_order')->get());

        $blocks = $sections->map(fn (HomepageSection $s) => $this->resolve($s, $catalog))->filter()->values();

        $seo = Seo::make(
            __('Free online games – play instantly'),
            __(':brand has free browser games for every mood: action, puzzle, racing, sports and more. Play instantly on desktop, tablet and phone.', ['brand' => config('platform.brand')]),
        )->jsonLd([
            '@type' => 'WebSite',
            'name' => config('platform.brand'),
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        return view('home', ['blocks' => $blocks, 'seo' => $seo, 'publicCount' => $catalog->publicCount()]);
    }

    /** @return array{section: HomepageSection, view: string, data: array}|null */
    private function resolve(HomepageSection $section, GameCatalog $catalog): ?array
    {
        $limit = (int) $section->cfg('limit', 16);
        $rail = fn ($games, string $icon, ?string $href = null) => $games->isEmpty() ? null : [
            'section' => $section, 'view' => 'rail', 'data' => ['games' => $games, 'icon' => $icon, 'href' => $href],
        ];

        return match ($section->type) {
            HomeSectionType::Hero => ($g = $catalog->featured($section->cfg('limit', 5)))->isEmpty() ? null
                : ['section' => $section, 'view' => 'hero', 'data' => ['games' => $g]],
            HomeSectionType::Trending => $rail($catalog->trending($limit), 'fire', route('games.index', ['sort' => 'trending'])),
            HomeSectionType::MostPlayed => $rail($catalog->mostPlayed($limit), 'trophy', route('games.index', ['sort' => 'popular'])),
            HomeSectionType::New => $rail($catalog->newest($limit), 'sparkle', route('games.index', ['sort' => 'new'])),
            HomeSectionType::EditorsPicks => $rail($catalog->editorsPicks($limit), 'star'),
            HomeSectionType::Multiplayer => $rail($catalog->multiplayer($limit), 'users', route('rooms.index')),
            HomeSectionType::MobileFriendly => $rail($catalog->mobileFriendly($limit), 'device', route('games.index', ['device' => 'mobile'])),
            HomeSectionType::Originals => $rail($catalog->originals($limit), 'bolt'),
            HomeSectionType::Flash => $rail($catalog->flash($limit), 'bolt'),
            HomeSectionType::Category => $this->categoryRail($section, $catalog, $limit),
            HomeSectionType::ContinuePlaying, HomeSectionType::RecentlyPlayed => ['section' => $section, 'view' => 'recent', 'data' => []],
            HomeSectionType::Recommended => ['section' => $section, 'view' => 'recommended', 'data' => [
                'games' => $this->recommended($catalog, $limit),
            ]],
            HomeSectionType::Random => ($g = $catalog->random()) ? ['section' => $section, 'view' => 'random', 'data' => ['game' => $g]] : null,
            HomeSectionType::CategoryGrid => ['section' => $section, 'view' => 'category-grid', 'data' => [
                'categories' => Category::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->withCount(['games' => fn ($q) => $q->public()])->get(),
            ]],
            HomeSectionType::SeoText => ['section' => $section, 'view' => 'seo-text', 'data' => []],
            HomeSectionType::Ad => ['section' => $section, 'view' => 'ad', 'data' => ['placement' => $section->cfg('placement', 'home_mid')]],
        };
    }

    private function categoryRail(HomepageSection $section, GameCatalog $catalog, int $limit): ?array
    {
        $slug = (string) $section->cfg('category');
        $category = Category::query()->where('slug', $slug)->first();
        $games = $catalog->inCategory($slug, $limit);

        return ($category && $games->isNotEmpty()) ? ['section' => $section, 'view' => 'rail', 'data' => [
            'games' => $games, 'icon' => 'gamepad', 'href' => $category->url(), 'fallbackTitle' => $category->tr('name'),
        ]] : null;
    }

    private function recommended(GameCatalog $catalog, int $limit)
    {
        $user = auth()->user();
        if (! $user || ! $user->personalization_enabled) {
            return $catalog->trending($limit);
        }
        $seeds = $user->favorites()->limit(20)->pluck('games.id')
            ->merge($user->plays()->latest('created_at')->limit(30)->pluck('game_id'))
            ->unique()->values()->all();

        return $catalog->recommendedFor($seeds, $limit);
    }
}
