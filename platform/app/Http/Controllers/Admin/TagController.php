<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Audit;
use App\Support\Translatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(Request $request): View
    {
        $q = Tag::query()->withCount('games')->orderByDesc('games_count')->orderBy('slug');
        if ($s = trim((string) $request->query('q'))) {
            $q->where('slug', 'like', '%'.Str::slug($s).'%');
        }

        return view('admin.tags.index', ['tags' => $q->paginate(50)->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tag = new Tag;
        $this->save($tag, $request);
        Audit::log('tag.create', $tag);

        return back()->with('status', 'Tag created.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->save($tag, $request);
        Audit::log('tag.update', $tag);

        return back()->with('status', 'Tag saved.');
    }

    private function save(Tag $tag, Request $request): void
    {
        $data = $request->validate([
            ...Translatable::rules('name', true, 60),
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tags', 'slug')->ignore($tag->id)],
        ]);
        $tag->name = Translatable::clean($data['name']);
        $tag->slug = $data['slug'] ?: Str::slug($data['name']['en']);
        $tag->save();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Audit::log('tag.delete', $tag, ['slug' => $tag->slug]);
        $tag->delete();

        return back()->with('status', 'Tag deleted.');
    }
}
