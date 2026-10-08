<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Seo;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->published()->whereIn('type', ['page', 'legal', 'faq', 'landing'])
            ->where('slug', $slug)->firstOrFail();

        $seo = Seo::make($page->tr('seo_title') ?: $page->tr('title'), $page->tr('seo_description') ?: ($page->tr('excerpt') ?: $page->tr('body')));

        return view('pages.show', compact('page', 'seo'));
    }
}
