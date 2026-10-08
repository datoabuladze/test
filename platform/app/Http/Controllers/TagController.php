<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tag;
use App\Support\Seo;
use Illuminate\View\View;

class TagController extends Controller
{
    public function show(Tag $tag): View
    {
        $games = Game::query()->public()->forCard()
            ->whereHas('tags', fn ($q) => $q->whereKey($tag->id))
            ->orderByDesc('popularity_score')->orderBy('id')
            ->paginate(config('platform.per_page'));
        abort_if($games->isEmpty() && $games->currentPage() > 1, 404);

        $seo = Seo::make(__(':tag games', ['tag' => $tag->tr('name')]), __('Free online :tag games you can play instantly.', ['tag' => $tag->tr('name')]));
        if ($games->total() < 3) {
            $seo->noindex();
        }

        return view('tags.show', compact('tag', 'games', 'seo'));
    }
}
