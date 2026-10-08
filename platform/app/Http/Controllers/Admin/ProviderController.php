<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\Audit;
use App\Services\GameCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProviderController extends Controller
{
    /** Non-secret provider defaults applied to imported rows that leave them blank. */
    public const DEFAULT_KEYS = ['license_type', 'license_url', 'hosting_method', 'commercial_use_allowed', 'ads_allowed', 'modifications_allowed', 'thumbnail_rights', 'embed_authorized'];

    public function index(): View
    {
        return view('admin.providers.index', [
            'providers' => Provider::query()->withCount(['games', 'games as public_games_count' => fn ($q) => $q->public()])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.providers.form', ['provider' => new Provider(['is_active' => true, 'adapter' => 'csv'])]);
    }

    public function edit(Provider $provider): View
    {
        return view('admin.providers.form', ['provider' => $provider]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = new Provider;
        $this->save($provider, $request);
        Audit::log('provider.create', $provider);

        return redirect()->route('admin.providers.index')->with('status', 'Provider created.');
    }

    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $this->save($provider, $request);
        Audit::log('provider.update', $provider);

        return redirect()->route('admin.providers.index')->with('status', 'Provider saved.');
    }

    private function save(Provider $provider, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('providers', 'slug')->ignore($provider->id)],
            'adapter' => ['required', Rule::in(array_keys(config('platform.import.adapters')))],
            'website_url' => ['nullable', 'url', 'max:255'],
            'agreement_url' => ['nullable', 'url', 'max:255'],
            'agreement_notes' => ['nullable', 'string', 'max:5000'],
            'allowed_embed_hosts' => ['nullable', 'string', 'max:2000'],
            'feed_url' => ['nullable', 'url:https', 'max:1024'],
            'license_type' => ['nullable', 'string', 'max:64'],
            'license_url' => ['nullable', 'url', 'max:1024'],
            'hosting_method' => ['nullable', Rule::in(['self_hosted', 'iframe_embed'])],
        ]);
        $hosts = collect(preg_split('/[\s,]+/', (string) ($data['allowed_embed_hosts'] ?? '')))
            ->map(fn ($h) => strtolower(trim($h)))->filter(fn ($h) => preg_match('/^(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+$/', $h))->unique()->values()->all();

        $settings = array_filter([
            'feed_url' => $data['feed_url'] ?? null,
            'license_type' => $data['license_type'] ?? null,
            'license_url' => $data['license_url'] ?? null,
            'hosting_method' => $data['hosting_method'] ?? null,
        ]);
        foreach (['commercial_use_allowed', 'ads_allowed', 'modifications_allowed', 'thumbnail_rights', 'embed_authorized'] as $f) {
            if ($request->boolean($f)) {
                $settings[$f] = true;
            }
        }
        $provider->fill(collect($data)->only(['name', 'slug', 'adapter', 'website_url', 'agreement_url', 'agreement_notes'])->all());
        $provider->allowed_embed_hosts = $hosts;
        $provider->settings = $settings;
        $provider->is_active = $request->boolean('is_active');
        $provider->save();
        GameCatalog::flush();
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        if ($provider->games()->exists()) {
            return back()->with('error', 'This provider still has games. Deactivate it instead, or move its games first.');
        }
        Audit::log('provider.delete', $provider, ['slug' => $provider->slug]);
        $provider->delete();

        return back()->with('status', 'Provider deleted.');
    }
}
