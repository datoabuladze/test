<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Seo;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = Page::query()->published()->whereIn('type', ['blog', 'news'])
            ->orderByDesc('published_at')->paginate(12);

        return view('blog.index', [
            'posts' => $posts,
            'seo' => Seo::make(__('News & guides'), __('Game news, new releases and playing guides from :brand.', ['brand' => config('platform.brand')])),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Page::query()->published()->whereIn('type', ['blog', 'news'])->where('slug', $slug)->with('author')->firstOrFail();
        $seo = Seo::make($post->tr('seo_title') ?: $post->tr('title'), $post->tr('seo_description') ?: ($post->tr('excerpt') ?: $post->tr('body')))
            ->type('article')
            ->jsonLd([
                '@type' => 'Article',
                'headline' => $post->tr('title'),
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => ['@type' => 'Organization', 'name' => config('platform.brand')],
            ]);

        return view('blog.show', compact('post', 'seo'));
    }
}
