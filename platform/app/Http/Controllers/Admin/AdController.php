<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AdStatDaily;
use App\Models\Game;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdController extends Controller
{
    public const TYPES = ['direct' => 'Direct banner', 'adsense' => 'Google AdSense', 'sponsored_game' => 'Sponsored game'];

    public function index(): View
    {
        $placements = AdPlacement::query()->withCount('campaigns')->orderBy('id')->get();
        $campaigns = AdCampaign::query()
            ->with(['placement:id,key,name', 'game:id,slug,title'])
            ->withSum('stats as impressions_total', 'impressions')
            ->withSum('stats as clicks_total', 'clicks')
            ->orderByDesc('is_active')->orderByDesc('priority')->orderByDesc('id')
            ->get();

        $since = now()->subDays(29)->toDateString();
        $totals = AdStatDaily::query()->where('date', '>=', $since)
            ->selectRaw('COALESCE(SUM(impressions),0) as impressions, COALESCE(SUM(clicks),0) as clicks, COALESCE(SUM(revenue),0) as revenue')
            ->first();

        return view('admin.ads.index', [
            'placements' => $placements,
            'campaigns' => $campaigns,
            'totals' => $totals,
            'types' => self::TYPES,
            'games' => Game::query()->public()->orderBy('slug')->limit(1000)->get(['id', 'slug', 'title']),
            'adsenseClient' => config('platform.ads.adsense_client'),
        ]);
    }

    public function placement(Request $request, AdPlacement $placement): RedirectResponse
    {
        $request->validate(['is_enabled' => ['nullable', 'boolean']]);
        $placement->update(['is_enabled' => $request->has('is_enabled') ? $request->boolean('is_enabled') : ! $placement->is_enabled]);
        Cache::forget("ads.placement.{$placement->key}");
        Audit::log('ads.placement', $placement, ['key' => $placement->key, 'enabled' => $placement->is_enabled]);

        return back()->with('status', "Placement “{$placement->name}” ".($placement->is_enabled ? 'enabled.' : 'disabled.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $campaign = AdCampaign::query()->create($data);
        Audit::log('ads.campaign.create', $campaign, ['type' => $campaign->type, 'placement' => $campaign->ad_placement_id]);

        return back()->with('status', 'Campaign created.');
    }

    public function update(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $data = $this->validated($request, $campaign);
        $old = $campaign->image_path;
        $campaign->update($data);
        if ($old && $old !== $campaign->image_path) {
            $this->deleteImage($old);
        }
        Audit::log('ads.campaign.update', $campaign, ['type' => $campaign->type, 'active' => $campaign->is_active]);

        return back()->with('status', 'Campaign saved.');
    }

    public function destroy(AdCampaign $campaign): RedirectResponse
    {
        Audit::log('ads.campaign.delete', $campaign, ['name' => $campaign->name]);
        $this->deleteImage($campaign->image_path);
        $campaign->delete();

        return back()->with('status', 'Campaign deleted.');
    }

    private function validated(Request $request, ?AdCampaign $campaign = null): array
    {
        $type = (string) $request->input('type');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'ad_placement_id' => ['required', 'integer', Rule::exists('ad_placements', 'id')],
            'game_id' => [Rule::requiredIf($type === 'sponsored_game'), 'nullable', 'integer', Rule::exists('games', 'id')],
            'image' => [Rule::requiredIf($type === 'direct' && ! $campaign?->image_path), 'nullable', 'file', 'image',
                'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:1024'],
            'target_url' => [Rule::requiredIf($type === 'direct'), 'nullable', 'string', 'max:1024', 'url:https', 'starts_with:https://'],
            'alt_text' => [Rule::requiredIf($type === 'direct'), 'nullable', 'string', 'max:160'],
            'adsense_slot' => [Rule::requiredIf($type === 'adsense'), 'nullable', 'string', 'regex:/^\d{6,20}$/'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'adsense_slot.regex' => 'The AdSense slot is the numeric data-ad-slot ID from your AdSense account.',
            'target_url.starts_with' => 'The target URL must use https://.',
        ]);

        $out = [
            'name' => $data['name'],
            'type' => $data['type'],
            'ad_placement_id' => (int) $data['ad_placement_id'],
            'game_id' => $data['type'] === 'sponsored_game' ? (int) $data['game_id'] : null,
            'target_url' => $data['type'] === 'direct' ? $data['target_url'] : null,
            'alt_text' => $data['type'] === 'direct' ? $data['alt_text'] : null,
            'adsense_slot' => $data['type'] === 'adsense' ? $data['adsense_slot'] : null,
            'priority' => (int) ($data['priority'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
        if ($request->hasFile('image')) {
            $out['image_path'] = $request->file('image')->store('ads', 'public');
        }

        return $out;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'ads/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
