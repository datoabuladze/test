<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/** Per-page SEO metadata rendered by layouts/partials/seo. */
class Seo
{
    public string $title = '';

    public string $description = '';

    public ?string $canonical = null;

    public ?string $image = null;

    public string $type = 'website';

    public bool $index = true;

    /** @var list<array> */
    public array $jsonLd = [];

    /** @var array<string, string>|null hreflang => url; null = derive from current route */
    public ?array $alternates = null;

    public static function make(string $title = '', string $description = ''): self
    {
        $seo = new self;
        $seo->title = $title;
        $seo->description = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? ''), 160);

        return $seo;
    }

    public function image(?string $url): self
    {
        $this->image = $url ? (Str::startsWith($url, 'http') ? $url : url($url)) : null;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(): self
    {
        $this->index = false;

        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function jsonLd(array $data): self
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $data;

        return $this;
    }

    /** @param array<string,string> $alternates */
    public function alternates(array $alternates): self
    {
        $this->alternates = $alternates;

        return $this;
    }

    public function fullTitle(): string
    {
        $brand = config('platform.brand');
        if ($this->title === '') {
            return $brand.' – '.__('Free online games');
        }

        return Str::contains($this->title, $brand) ? $this->title : $this->title.' | '.$brand;
    }

    public function canonicalUrl(): string
    {
        if ($this->canonical) {
            return $this->canonical;
        }
        $page = (int) request()->query('page', 1);

        return url()->current().($page > 1 ? '?page='.$page : '');
    }

    /** @return array<string, string> */
    public function alternateUrls(): array
    {
        if ($this->alternates !== null) {
            return $this->alternates;
        }
        $route = Route::current();
        if (! $route || ! $route->getName() || ! in_array('locale', $route->parameterNames(), true)) {
            return [];
        }
        $params = $route->parameters();
        $urls = [];
        foreach (config('platform.locales') as $code => $meta) {
            $urls[$meta['hreflang']] = route($route->getName(), array_merge($params, ['locale' => $code]));
        }
        $urls['x-default'] = $urls[config('platform.default_locale')] ?? reset($urls);

        return $urls;
    }

    public static function localizedCurrentUrl(string $locale): string
    {
        $route = Route::current();
        if ($route && $route->getName() && in_array('locale', $route->parameterNames(), true)) {
            $url = route($route->getName(), array_merge($route->parameters(), ['locale' => $locale]));
            $query = request()->getQueryString();

            return $query ? $url.'?'.$query : $url;
        }

        return route('home', ['locale' => $locale]);
    }
}
