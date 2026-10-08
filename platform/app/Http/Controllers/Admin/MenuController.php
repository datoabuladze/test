<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\Audit;
use App\Support\Translatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuController extends Controller
{
    public const MENUS = [
        'header' => 'Header',
        'footer_main' => 'Footer – main links',
        'footer_legal' => 'Footer – legal links',
    ];

    public function index(): View
    {
        $items = MenuItem::query()->orderBy('sort_order')->orderBy('id')->get()->groupBy('menu');

        return view('admin.menus.index', ['menus' => self::MENUS, 'items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= (int) MenuItem::query()->where('menu', $data['menu'])->max('sort_order') + 1;
        $item = MenuItem::query()->create($data);
        $this->flush();
        Audit::log('menu.create', $item, ['menu' => $item->menu, 'url' => $item->url]);

        return back()->with('status', 'Menu item added.');
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= $item->sort_order;
        $item->update($data);
        $this->flush();
        Audit::log('menu.update', $item, ['menu' => $item->menu, 'url' => $item->url, 'enabled' => $item->is_enabled]);

        return back()->with('status', 'Menu item saved.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        Audit::log('menu.delete', $item, ['menu' => $item->menu, 'url' => $item->url]);
        $item->delete();
        $this->flush();

        return back()->with('status', 'Menu item removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'menu' => ['required', Rule::in(array_keys(self::MENUS))],
            ...Translatable::rules('label', required: true, max: 80),
            // Relative path (locale prefix is added on render) or an absolute http(s) URL. No other schemes.
            'url' => ['required', 'string', 'max:512', function ($attr, $value, $fail) {
                $v = (string) $value;
                if (preg_match('#^https?://#i', $v)) {
                    if (! filter_var($v, FILTER_VALIDATE_URL)) {
                        $fail('The URL is not valid.');
                    }
                } elseif (! preg_match('#^/?[A-Za-z0-9\-._~/?=&%+]*$#', $v) || str_starts_with($v, '//')) {
                    $fail('Use a relative path like "p/about" or a full https:// URL.');
                }
            }],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        return [
            'menu' => $data['menu'],
            'label' => Translatable::clean($request->input('label')),
            'url' => $data['url'],
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            'is_enabled' => $request->boolean('is_enabled'),
        ];
    }

    private function flush(): void
    {
        Cache::forget('menus.all');
    }
}
