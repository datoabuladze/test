<?php

namespace App\Services\Import;

use App\Enums\GameEngine;
use App\Models\Category;
use App\Models\Game;
use App\Models\Provider;
use Illuminate\Support\Str;

/**
 * Turns canonical rows into validated game attributes. Licensing is mandatory: a row
 * without a license, a source and a permitted hosting method is rejected, and embed
 * URLs must be HTTPS on a host the provider allow-lists.
 */
class ImportNormalizer
{
    /** Canonical row fields. Per-locale variants use a suffix: title_ka, description_ru, ... */
    public const FIELDS = [
        'external_id', 'title', 'short_description', 'description', 'instructions', 'controls',
        'engine', 'embed_url', 'thumbnail_url', 'categories', 'tags', 'width', 'height', 'orientation',
        'devices', 'input_types', 'languages', 'developer', 'developer_url', 'source_url',
        'license_type', 'license_url', 'attribution_text', 'hosting_method', 'commercial_use_allowed', 'ads_allowed',
        'modifications_allowed', 'thumbnail_rights', 'embed_authorized', 'min_age', 'is_mobile_friendly', 'is_multiplayer',
    ];

    private const TRANSLATABLE = ['title', 'short_description', 'description', 'instructions', 'controls'];

    private const FLAGS = ['commercial_use_allowed', 'ads_allowed', 'modifications_allowed', 'thumbnail_rights', 'embed_authorized', 'is_mobile_friendly', 'is_multiplayer'];

    private ?array $categorySlugs = null;

    /** @return array{data: array, errors: list<string>, warnings: list<string>} */
    public function normalize(array $row, ?Provider $provider): array
    {
        $errors = [];
        $warnings = [];
        $row = array_change_key_case($row, CASE_LOWER);
        $defaults = $provider?->settings ?? [];
        $get = fn (string $k) => isset($row[$k]) && $row[$k] !== '' ? $row[$k] : ($defaults[$k] ?? null);
        $locales = array_keys(config('platform.locales'));

        $data = [];
        foreach (self::TRANSLATABLE as $f) {
            $values = [];
            foreach ($locales as $l) {
                $v = $l === 'en' ? ($row[$f] ?? $row[$f.'_en'] ?? null) : ($row[$f.'_'.$l] ?? null);
                $v = is_string($v) ? trim(strip_tags($v)) : null;
                if ($v) {
                    $values[$l] = Str::limit($v, $f === 'title' ? 120 : 10000, '');
                }
            }
            $data[$f] = $values ?: null;
        }
        if (empty($data['title']['en'])) {
            $errors[] = 'Missing English title.';
        }

        $data['external_id'] = $get('external_id') ? Str::limit((string) $get('external_id'), 191, '') : null;
        $engine = GameEngine::tryFrom(strtolower((string) ($get('engine') ?: 'iframe')));
        if (! $engine || $engine === GameEngine::Original) {
            $errors[] = 'Unsupported engine "'.$get('engine').'". Use iframe, html5, phaser, unity or ruffle.';
            $engine = GameEngine::Iframe;
        }
        $data['engine'] = $engine->value;

        $embed = trim((string) $get('embed_url'));
        if ($engine === GameEngine::Iframe) {
            $host = parse_url($embed, PHP_URL_HOST);
            if (! $embed || ! str_starts_with($embed, 'https://') || ! $host) {
                $errors[] = 'Embed URL must be an HTTPS URL.';
            } elseif (! $provider || ! $provider->allowsEmbedHost($host)) {
                $errors[] = "Embed host $host is not on the provider's allow-list.";
            }
            $data['embed_url'] = $embed ?: null;
        } else {
            $warnings[] = 'Self-hosted engine: upload the game files on the game page after import.';
            $data['embed_url'] = null;
        }

        foreach (['source_url', 'license_url', 'developer_url', 'thumbnail_url'] as $u) {
            $v = trim((string) $get($u));
            $data[$u] = filter_var($v, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $v) ? $v : null;
            if ($v && ! $data[$u]) {
                $warnings[] = "Ignored invalid $u.";
            }
        }
        $data['license_type'] = $get('license_type') ? Str::limit((string) $get('license_type'), 64, '') : null;
        $data['hosting_method'] = in_array($get('hosting_method'), ['self_hosted', 'iframe_embed'], true)
            ? $get('hosting_method') : ($engine === GameEngine::Iframe ? 'iframe_embed' : null);
        if (! $data['license_type']) {
            $errors[] = 'Missing license type (set it per row or as a provider default).';
        }
        if (! $data['source_url']) {
            $errors[] = 'Missing source URL.';
        }
        if ($engine === GameEngine::Iframe && $data['hosting_method'] !== 'iframe_embed') {
            $errors[] = 'Hosting method must be iframe_embed for embedded games.';
        }
        if ($engine !== GameEngine::Iframe && $data['hosting_method'] !== 'self_hosted') {
            $errors[] = 'Self-hosted games need hosting_method=self_hosted (the license must allow redistribution).';
        }

        foreach (self::FLAGS as $f) {
            $data[$f] = filter_var($get($f), FILTER_VALIDATE_BOOL);
        }
        if ($engine === GameEngine::Iframe && ! $data['embed_authorized']) {
            $errors[] = 'embed_authorized must be true: the provider must permit embedding.';
        }
        if ($data['thumbnail_url'] && ! $data['thumbnail_rights']) {
            $warnings[] = 'Thumbnail will not be imported because thumbnail_rights is not true.';
        }

        $data['developer'] = $get('developer') ? Str::limit(strip_tags((string) $get('developer')), 191, '') : null;
        $data['attribution_text'] = $get('attribution_text') ? Str::limit(strip_tags((string) $get('attribution_text')), 2000, '') : null;
        $data['width'] = $this->int($get('width'), 200, 4096);
        $data['height'] = $this->int($get('height'), 200, 4096);
        $data['min_age'] = $this->int($get('min_age'), 0, 18) ?? 0;
        $data['orientation'] = in_array($get('orientation'), ['any', 'landscape', 'portrait'], true) ? $get('orientation') : 'any';
        $data['devices'] = $this->list($get('devices'), ['desktop', 'tablet', 'mobile']);
        $data['input_types'] = $this->list($get('input_types'), ['keyboard', 'mouse', 'touch', 'gamepad']);
        $data['languages'] = $this->list($get('languages'), $locales);

        [$cats, $unknown] = $this->categories($get('categories'));
        $data['categories'] = $cats;
        if ($unknown) {
            $warnings[] = 'Unknown categories ignored: '.implode(', ', $unknown).'.';
        }
        if (! $cats) {
            $warnings[] = 'No known category; assign one before publishing.';
        }
        $data['tags'] = array_slice(array_values(array_filter(array_map(
            fn ($t) => Str::limit(trim(strip_tags($t)), 40, ''), preg_split('/[|,]/', (string) $get('tags'))
        ))), 0, 15);

        return ['data' => $data, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** Returns the id of an existing game this row duplicates, or null. */
    public function findDuplicate(array $data, ?Provider $provider): ?int
    {
        if ($provider && $data['external_id']) {
            $id = Game::withTrashed()->where('provider_id', $provider->id)->where('external_id', $data['external_id'])->value('id');
            if ($id) {
                return $id;
            }
        }
        if (! empty($data['embed_url'])) {
            $id = Game::withTrashed()->where('embed_url', $data['embed_url'])->value('id');
            if ($id) {
                return $id;
            }
        }
        $slug = Str::slug($data['title']['en'] ?? '');

        return $slug ? Game::withTrashed()->where('slug', $slug)->value('id') : null;
    }

    private function int(mixed $v, int $min, int $max): ?int
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }

        return max($min, min($max, (int) $v));
    }

    private function list(mixed $v, array $allowed): array
    {
        $items = is_array($v) ? $v : preg_split('/[|,]/', strtolower((string) $v));

        return array_values(array_intersect($allowed, array_map('trim', $items)));
    }

    /** @return array{0: list<string>, 1: list<string>} [known slugs, unknown names] */
    private function categories(mixed $v): array
    {
        $this->categorySlugs ??= Category::query()->pluck('slug')->all();
        $known = [];
        $unknown = [];
        foreach (is_array($v) ? $v : preg_split('/[|,]/', (string) $v) as $name) {
            $slug = Str::slug(trim((string) $name));
            if ($slug === '') {
                continue;
            }
            in_array($slug, $this->categorySlugs, true) ? $known[] = $slug : $unknown[] = trim((string) $name);
        }

        return [array_values(array_unique($known)), $unknown];
    }
}
