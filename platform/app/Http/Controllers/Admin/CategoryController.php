<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Audit;
use App\Services\GameCatalog;
use App\Support\Translatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $all = Category::query()->withCount('games')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.categories.index', [
            'roots' => $all->whereNull('parent_id')->values(),
            'children' => $all->whereNotNull('parent_id')->groupBy('parent_id'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Category(['is_active' => true, 'show_in_menu' => true]));
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    private function form(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()->whereNull('parent_id')->whereKeyNot($category->id)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = new Category(['sort_order' => (int) Category::query()->max('sort_order') + 1]);
        $this->save($category, $request);
        Audit::log('category.create', $category);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->save($category, $request);
        Audit::log('category.update', $category);

        return redirect()->route('admin.categories.index')->with('status', 'Category saved.');
    }

    private function save(Category $category, Request $request): void
    {
        $data = $request->validate([
            ...Translatable::rules('name', true, 80),
            ...Translatable::rules('description', false, 2000),
            ...Translatable::rules('seo_title', false, 70),
            ...Translatable::rules('seo_description', false, 170),
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn([$category->id])],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        foreach (['name', 'description', 'seo_title', 'seo_description'] as $f) {
            $category->setAttribute($f, Translatable::clean($data[$f] ?? null));
        }
        $category->fill(collect($data)->only(['slug', 'parent_id', 'icon', 'color'])->all());
        foreach (['is_active', 'show_in_menu', 'show_on_home'] as $flag) {
            $category->setAttribute($flag, $request->boolean($flag));
        }
        $category->save();
        GameCatalog::flush();
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->with('error', 'Move or delete the subcategories first.');
        }
        Audit::log('category.delete', $category, ['slug' => $category->slug, 'games' => $category->games()->count()]);
        $category->delete();
        GameCatalog::flush();

        return back()->with('status', 'Category deleted. Its games were kept.');
    }

    /** Drag-and-drop ordering: receives {ids: [...]} for one level of the tree. */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'exists:categories,id']])['ids'];
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Category::query()->whereKey($id)->update(['sort_order' => $i]);
            }
        });
        Cache::forget('categories.tree');
        GameCatalog::flush();
        Audit::log('category.reorder', null, ['ids' => $ids]);

        return response()->json(['ok' => true]);
    }
}
