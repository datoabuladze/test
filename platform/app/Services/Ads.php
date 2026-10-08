<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AdStatDaily;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Serves ads for enabled placements. Impressions counted here are "served"
 * impressions (the ad was rendered into a page response), never simulated.
 */
class Ads
{
    public function forPlacement(string $key): ?AdCampaign
    {
        $placement = Cache::remember("ads.placement.$key", 300, fn () => AdPlacement::query()->where('key', $key)->first());
        if (! $placement || ! $placement->is_enabled) {
            return null;
        }
        $campaign = AdCampaign::query()->running()->where('ad_placement_id', $placement->id)
            ->with('game')->orderByDesc('priority')->inRandomOrder()->first();
        if (! $campaign || ! $this->renderable($campaign)) {
            return null;
        }
        // AdSense counts its own impressions (and only renders after consent), so ours are
        // recorded for house and sponsored campaigns only.
        if ($campaign->type !== 'adsense') {
            $this->record($campaign, 'impressions');
        }

        return $campaign;
    }

    /** Whether the ad-slot component would show anything for this campaign. */
    public function renderable(AdCampaign $campaign): bool
    {
        return match (true) {
            $campaign->type === 'adsense' => (bool) config('platform.ads.adsense_client'),
            $campaign->type === 'sponsored_game' => (bool) $campaign->game,
            default => (bool) $campaign->image_path,
        };
    }

    public function record(AdCampaign $campaign, string $column): void
    {
        $date = now()->toDateString();
        $updated = AdStatDaily::query()->where('ad_campaign_id', $campaign->id)->where('date', $date)
            ->update([$column => DB::raw("$column + 1")]);
        if (! $updated) {
            AdStatDaily::query()->insertOrIgnore(['ad_campaign_id' => $campaign->id, 'date' => $date, $column => 1]);
        }
    }
}
