<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Audit;
use App\Support\Translatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), Page::TYPES, true) ? $request->query('type') : null;
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $q = trim((string) $request->query('q', ''));

        $pages = Page::query()
            ->select(['id', 'slug', 'type', 'title', 'status', 'published_at', 'author_id', 'updated_at'])
            ->with('author:id,nickname')
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('slug', 'like', "%$q%")->orWhere('title', 'like', "%$q%")))
            ->orderByDesc('updated_at')
            ->paginate(25)->withQueryString();

        return view('admin.pages.index', ['pages' => $pages, 'types' => Page::TYPES, 'type' => $type, 'status' => $status, 'q' => $q]);
    }

    public function create(Request $request): View
    {
        $page = new Page(['type' => in_array($request->query('type'), Page::TYPES, true) ? $request->query('type') : 'page', 'status' => 'draft']);

        return view('admin.pages.form', ['page' => $page, 'types' => Page::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $page = Page::query()->create($data);
        Audit::log('page.create', $page, ['type' => $page->type, 'slug' => $page->slug]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page, 'types' => Page::TYPES]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        Audit::log('page.update', $page, ['type' => $page->type, 'slug' => $page->slug, 'status' => $page->status]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        Audit::log('page.delete', $page, ['type' => $page->type, 'slug' => $page->slug]);
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        } elseif (is_array($request->input('title'))) {
            $request->merge(['slug' => Str::slug((string) ($request->input('title')['en'] ?? ''))]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(Page::TYPES)],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->where('type', $request->input('type'))->ignore($page?->id)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            ...Translatable::rules('title', required: true, max: 255),
            ...Translatable::rules('excerpt', max: 1000),
            ...Translatable::rules('body', required: true, max: 100000),
            ...Translatable::rules('seo_title', max: 255),
            ...Translatable::rules('seo_description', max: 500),
        ]);

        $publishedAt = $data['published_at'] ?? null;
        if ($data['status'] === 'published' && ! $publishedAt) {
            $publishedAt = $page?->published_at ?? now();
        }

        return [
            'type' => $data['type'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'title' => Translatable::clean($request->input('title')),
            'excerpt' => Translatable::clean($request->input('excerpt')),
            'body' => Translatable::clean($request->input('body')),
            'seo_title' => Translatable::clean($request->input('seo_title')),
            'seo_description' => Translatable::clean($request->input('seo_description')),
        ];
    }
}
