<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\AdCampaign;
use App\Services\Ads;
use Illuminate\Http\RedirectResponse;

class AdController extends Controller
{
    public function click(AdCampaign $campaign, Ads $ads): RedirectResponse
    {
        abort_unless($campaign->is_active, 404);
        $ads->record($campaign, 'clicks');

        if ($campaign->type === 'sponsored_game' && $campaign->game) {
            return redirect($campaign->game->url(SetLocale::preferred(request())));
        }
        $url = (string) $campaign->target_url;
        abort_unless(str_starts_with($url, 'https://') || str_starts_with($url, 'http://'), 404);

        return redirect()->away($url);
    }
}
