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
        if ($campaign) {
            $this->record($campaign, 'impressions');
        }

        return $campaign;
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
