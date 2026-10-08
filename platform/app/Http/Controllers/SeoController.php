<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Game;
use App\Models\Page;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt and XML sitemaps. Sitemaps contain only public, canonical,
 * indexable URLs (published games/pages, active categories with games),
 * one sitemap per locale and type, with hreflang alternates.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (app()->environment('production') && ! config('platform.block_indexing', false)) {
            $lines = array_merge($lines, [
                'Disallow: /admin', 'Disallow: /api/', 'Disallow: /frame/', 'Disallow: /*/account', 'Disallow: /*/search',
                'Disallow: /*/play-together/', 'Disallow: /ad/',
            ]);
        } else {
            $lines[] = 'Disallow: /'; // never index staging/dev environments
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap.index');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemapIndex(): Response
    {
        $xml = Cache::remember('sitemap.index', 3600, function () {
            $lastGame = Game::query()->public()->max('updated_at');
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach (array_keys(config('platform.locales')) as $locale) {
                foreach (['static', 'categories', 'games', 'pages'] as $type) {
                    $out .= '<sitemap><loc>'.e(route('sitemap.show', ['locale' => $locale, 'type' => $type])).'</loc>'
                        .($lastGame ? '<lastmod>'.date(DATE_ATOM, strtotime((string) $lastGame)).'</lastmod>' : '').'</sitemap>'."\n";
                }
            }

            return $out.'</sitemapindex>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemap(string $locale, string $type): Response
    {
        $xml = Cache::remember("sitemap.$locale.$type", 3600, function () use ($locale, $type) {
            app()->setLocale($locale);
            $entries = match ($type) {
                'static' => $this->staticEntries(),
                'categories' => $this->categoryEntries(),
                'games' => $this->gameEntries(),
                'pages' => $this->pageEntries(),
            };
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
            foreach ($entries as $entry) {
                $out .= '<url><loc>'.e($entry['loc']($locale)).'</loc>';
                if (! empty($entry['lastmod'])) {
                    $out .= '<lastmod>'.$entry['lastmod'].'</lastmod>';
                }
                foreach (config('platform.locales') as $code => $meta) {
                    $out .= '<xhtml:link rel="alternate" hreflang="'.$meta['hreflang'].'" href="'.e($entry['loc']($code)).'"/>';
                }
                if (! empty($entry['image'])) {
                    $out .= '<image:image><image:loc>'.e($entry['image']).'</image:loc></image:image>';
                }
                $out .= "</url>\n";
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function staticEntries(): array
    {
        return array_map(fn ($name) => ['loc' => fn ($l) => route($name, ['locale' => $l])],
            ['home', 'games.index', 'categories.index', 'leaderboards.index', 'rooms.index', 'blog.index']);
    }

    private function categoryEntries(): array
    {
        return Category::query()->where('is_active', true)
            ->whereHas('games', fn ($q) => $q->public())
            ->orderBy('sort_order')->get()
            ->map(fn (Category $c) => ['loc' => fn ($l) => route('categories.show', ['locale' => $l, 'category' => $c->slug]),
                'lastmod' => $c->updated_at?->toAtomString()])->all();
    }

    private function gameEntries(): array
    {
        $entries = [];
        Game::query()->public()->select(['id', 'slug', 'thumbnail_path', 'updated_at'])->orderBy('id')
            ->chunk(1000, function ($games) use (&$entries) {
                foreach ($games as $g) {
                    $entries[] = [
                        'loc' => fn ($l) => route('games.show', ['locale' => $l, 'game' => $g->slug]),
                        'lastmod' => $g->updated_at?->toAtomString(),
                        'image' => $g->thumbnailUrl() ? url($g->thumbnailUrl()) : null,
                    ];
                }
            });

        return $entries;
    }

    private function pageEntries(): array
    {
        return Page::query()->published()->get()->map(fn (Page $p) => [
            'loc' => fn ($l) => $p->url($l), 'lastmod' => $p->updated_at?->toAtomString(),
        ])->all();
    }
}
