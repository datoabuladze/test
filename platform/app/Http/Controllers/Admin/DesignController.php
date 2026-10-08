<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ThemeVersion;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DesignController extends Controller
{
    public const COLORS = [
        'brand_primary' => ['Primary', '--brand-primary'],
        'brand_secondary' => ['Secondary', '--brand-secondary'],
        'brand_accent' => ['Accent', '--brand-accent'],
        'surface_dark' => ['Dark background', '--surface-bg'],
    ];

    public const CHOICES = [
        'radius' => ['sm', 'md', 'lg', 'xl'],
        'card_style' => ['flat', 'glow', 'outline'],
        'density' => ['compact', 'comfortable', 'spacious'],
        'default_mode' => ['dark', 'light', 'system'],
    ];

    public const FONTS = ['Outfit Variable', 'Inter Variable', 'system-ui'];

    public function index(): View
    {
        $versions = ThemeVersion::query()->with('creator:id,nickname')->latest('id')->limit(50)->get();

        return view('admin.design.index', [
            'tokens' => ThemeVersion::activeTokens(),
            'versions' => $versions,
            'colors' => self::COLORS,
            'choices' => self::CHOICES,
            'fonts' => self::FONTS,
            'branding' => (array) Setting::get('branding', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules = ['note' => ['nullable', 'string', 'max:120'], 'activate' => ['nullable', 'boolean']];
        foreach (array_keys(self::COLORS) as $key) {
            $rules[$key] = $hex;
        }
        foreach (self::CHOICES as $key => $options) {
            $rules[$key] = ['required', Rule::in($options)];
        }
        $rules['font_display'] = ['required', Rule::in(self::FONTS)];
        $rules['font_body'] = ['required', Rule::in(self::FONTS)];
        $data = $request->validate($rules);

        $tokens = [];
        foreach (array_keys(ThemeVersion::DEFAULTS) as $key) {
            $tokens[$key] = isset(self::COLORS[$key]) ? strtolower($data[$key]) : $data[$key];
        }

        $version = DB::transaction(function () use ($request, $tokens, $data) {
            $activate = $request->boolean('activate');
            if ($activate) {
                ThemeVersion::query()->where('is_active', true)->update(['is_active' => false]);
            }

            return ThemeVersion::query()->create([
                'tokens' => $tokens,
                'note' => $data['note'] ?? null,
                'is_active' => $activate,
                'created_by' => $request->user()->id,
            ]);
        });
        Cache::forget('theme.active');
        Audit::log('theme.create', $version, ['active' => $version->is_active]);

        return back()->with('status', $version->is_active ? "Theme version #{$version->id} saved and activated." : "Theme version #{$version->id} saved as a draft.");
    }

    public function activate(ThemeVersion $version): RedirectResponse
    {
        DB::transaction(function () use ($version) {
            ThemeVersion::query()->whereKeyNot($version->id)->where('is_active', true)->update(['is_active' => false]);
            $version->update(['is_active' => true]);
        });
        Cache::forget('theme.active');
        Audit::log('theme.activate', $version);

        return back()->with('status', "Theme version #{$version->id} is now active.");
    }

    public function branding(Request $request): RedirectResponse
    {
        // SVG is intentionally not accepted: uploaded SVGs can carry scripts.
        $image = ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:1024'];
        $request->validate([
            'logo' => [...$image, 'dimensions:max_width=2000,max_height=1000'],
            'favicon' => [...$image, 'dimensions:max_width=1024,max_height=1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $branding = (array) Setting::get('branding', []);
        $changed = [];
        foreach (['logo', 'favicon'] as $key) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store('branding', 'public');
                $this->deleteOld($branding[$key] ?? null);
                $branding[$key] = $path;
                $changed[$key] = $path;
            } elseif ($request->boolean('remove_'.$key) && ! empty($branding[$key])) {
                $this->deleteOld($branding[$key]);
                unset($branding[$key]);
                $changed[$key] = null;
            }
        }

        if (! $changed) {
            return back()->with('status', 'Nothing to update.');
        }

        // The layout view composer reads Setting::get('branding') as ['logo' => path, 'favicon' => path].
        Setting::put('branding', $branding);
        Audit::log('branding.update', null, $changed);

        return back()->with('status', 'Branding updated.');
    }

    private function deleteOld(?string $path): void
    {
        if ($path && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
