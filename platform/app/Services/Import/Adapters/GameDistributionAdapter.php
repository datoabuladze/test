<?php

namespace App\Services\Import\Adapters;

use App\Models\ImportBatch;
use App\Services\Import\ImportException;
use Illuminate\Support\Facades\Http;

/**
 * Reads a provider catalog feed (JSON) from the URL saved in the provider's settings
 * ("feed_url") and maps the field names used by GameDistribution's catalog export.
 *
 * Use only with an approved publisher account and within the provider's terms. The
 * field mapping follows the provider's documented export format but has NOT been
 * verified against a live feed in this repository; check the preview before running.
 * Every imported game is a draft with unverified rights until a staff member reviews it.
 */
class GameDistributionAdapter extends JsonAdapter
{
    public function label(): string
    {
        return 'GameDistribution catalog feed';
    }

    public function needsFile(): bool
    {
        return false;
    }

    public function rows(ImportBatch $batch): iterable
    {
        $provider = $batch->provider;
        $url = (string) ($provider?->settings['feed_url'] ?? '');
        if (! $provider?->is_active) {
            throw new ImportException('The provider is inactive.');
        }
        if (! str_starts_with($url, 'https://')) {
            throw new ImportException('Set an HTTPS "feed_url" in the provider settings first.');
        }
        try {
            $res = Http::timeout(20)->accept('application/json')->withUserAgent(config('platform.brand').' catalog import')->get($url);
        } catch (\Throwable $e) {
            throw new ImportException('Feed request failed: '.class_basename($e));
        }
        if (! $res->successful()) {
            throw new ImportException('Feed returned HTTP '.$res->status().'.');
        }
        if (strlen($res->body()) > 50 * 1024 * 1024) {
            throw new ImportException('Feed is larger than 50 MB.');
        }

        return $this->decode($res->body());
    }

    public function map(array $raw): array
    {
        $first = fn (array $keys) => collect($keys)->map(fn ($k) => data_get($raw, $k))->first(fn ($v) => $v !== null && $v !== '');
        $list = function ($v) {
            if (is_array($v)) {
                return implode('|', array_map(fn ($x) => is_array($x) ? ($x['name'] ?? '') : (string) $x, $v));
            }

            return (string) $v;
        };
        $mobile = $first(['Mobile', 'mobile', 'MobileReady']);
        $assets = $first(['Asset', 'Assets', 'assets']);

        return [
            'external_id' => (string) $first(['Md5', 'md5', 'Id', 'id']),
            'title' => (string) $first(['Title', 'title']),
            'description' => (string) $first(['Description', 'description']),
            'instructions' => (string) $first(['Instructions', 'instructions']),
            'engine' => 'iframe',
            'embed_url' => (string) $first(['Url', 'url', 'EmbedUrl']),
            // The provider's page for the game is the source; fall back to the embed URL.
            'source_url' => (string) ($first(['GameUrl', 'gameUrl', 'Link', 'link']) ?? $first(['Url', 'url', 'EmbedUrl'])),
            'thumbnail_url' => is_array($assets) ? (string) ($assets[0] ?? '') : (string) $assets,
            'categories' => $list($first(['Category', 'category', 'Categories']) ?? ''),
            'tags' => $list($first(['Tag', 'tags', 'Tags']) ?? ''),
            'width' => $first(['Width', 'width']),
            'height' => $first(['Height', 'height']),
            'devices' => filter_var($mobile, FILTER_VALIDATE_BOOL) ? 'desktop|tablet|mobile' : 'desktop',
            'is_mobile_friendly' => filter_var($mobile, FILTER_VALIDATE_BOOL) ? '1' : '0',
            'developer' => (string) $first(['Company', 'company', 'Developer']),
            // Licensing comes from the provider agreement, not the feed: the normalizer fills
            // license/hosting defaults from the provider record and marks rights unverified.
        ];
    }
}
