<?php

namespace App\Services;

use App\Models\Game;

/** Validates third-party embed URLs against the provider's allow-list. */
class EmbedValidator
{
    public function isAllowed(Game $game): bool
    {
        $url = (string) $game->embed_url;
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $provider = $game->provider;

        return $provider !== null && $provider->allowsEmbedHost($parts['host']);
    }
}
