<?php

namespace Database\Seeders;

use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Provider;
use App\Models\Tag;
use App\Services\GameCatalog;
use Illuminate\Database\Seeder;

/**
 * Registers the first-party games in public/games/originals/<key>/meta.json.
 * These are original works, so rights are verified at source; launch status
 * starts "untested" until the browser smoke test (games:smoke) confirms them.
 */
class OriginalGamesSeeder extends Seeder
{
    public function run(): void
    {
        $provider = Provider::query()->where('slug', 'original')->firstOrFail();
        $dir = public_path('games/originals');
        $categoryIds = Category::query()->pluck('id', 'slug');
        $count = 0;

        foreach (glob($dir.'/*/meta.json') ?: [] as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (! is_array($meta) || empty($meta['key'])) {
                $this->command?->warn("Skipping invalid metadata: $file");

                continue;
            }
            $key = $meta['key'];
            if (! is_file("$dir/$key/index.html")) {
                continue;
            }

            $game = Game::withTrashed()->firstOrNew(['slug' => $meta['slug'] ?? $key]);
            $isNew = ! $game->exists;
            $game->fill([
                'title' => $meta['title'],
                'short_description' => $meta['short_description'] ?? null,
                'description' => $meta['description'] ?? null,
                'instructions' => $meta['instructions'] ?? null,
                'controls' => $meta['controls'] ?? null,
                'engine' => GameEngine::Original,
                'entry_path' => "games/originals/$key/index.html",
                'engine_config' => array_filter([
                    'max_points_per_second' => $meta['max_points_per_second'] ?? null,
                    'max_score' => $meta['max_score'] ?? null,
                    'verifier' => $meta['verifier'] ?? null,
                ], fn ($v) => $v !== null),
                'width' => $meta['width'] ?? null,
                'height' => $meta['height'] ?? null,
                'orientation' => $meta['orientation'] ?? 'any',
                'thumbnail_path' => is_file("$dir/$key/thumb.svg") ? "/games/originals/$key/thumb.svg" : null,
                'thumbnail_color' => $meta['color'] ?? null,
                'provider_id' => $provider->id,
                'external_id' => $key,
                'developer' => config('platform.brand').' Studio',
                'license_type' => 'Original work',
                'license_notes' => 'Code, art and sound created for this platform. All rights reserved by the operator.',
                'hosting_method' => 'self_hosted',
                'commercial_use_allowed' => true,
                'ads_allowed' => true,
                'modifications_allowed' => true,
                'thumbnail_rights' => true,
                'embed_authorized' => true,
                'rights_status' => RightsStatus::Verified,
                'input_types' => $meta['input_types'] ?? null,
                'devices' => $meta['devices'] ?? null,
                'languages' => ['en', 'ka', 'tr', 'ru'],
                'min_age' => $meta['min_age'] ?? 0,
                'difficulty' => $meta['difficulty'] ?? null,
                'session_length' => $meta['session_length'] ?? null,
                'is_featured' => (bool) ($meta['featured'] ?? false),
                'is_editors_pick' => (bool) ($meta['editors_pick'] ?? false),
                'is_multiplayer' => (bool) ($meta['is_multiplayer'] ?? false),
                'is_mobile_friendly' => (bool) ($meta['is_mobile_friendly'] ?? in_array('mobile', $meta['devices'] ?? [], true)),
                'is_original' => true,
                'score_mode' => $meta['score_mode'] ?? 'none',
                'status' => GameStatus::Published,
            ]);
            if ($isNew || ! $game->published_at) {
                // Stagger release times slightly so "new" ordering is stable.
                $game->published_at = now()->subMinutes(60 - $count);
            }
            $game->rights_verified_at ??= now();
            $game->deleted_at = null;
            $game->save();

            $sync = [];
            foreach ($meta['categories'] ?? [] as $slug) {
                if (isset($categoryIds[$slug])) {
                    $sync[$categoryIds[$slug]] = ['is_primary' => $slug === ($meta['primary_category'] ?? null)];
                }
            }
            if (isset($categoryIds['html5'])) {
                $sync[$categoryIds['html5']] = ['is_primary' => false];
            }
            if (($meta['is_mobile_friendly'] ?? false) && isset($categoryIds['mobile'])) {
                $sync[$categoryIds['mobile']] = ['is_primary' => false];
            }
            $game->categories()->sync($sync);

            $tagIds = [];
            foreach ($meta['tags'] ?? [] as $tag) {
                $tagIds[] = Tag::query()->updateOrCreate(['slug' => $tag['slug']], ['name' => $tag['name']])->id;
            }
            $game->tags()->sync($tagIds);
            $game->refreshSearchText();
            $count++;
        }

        GameCatalog::flush();
        $this->command?->info("Registered $count original games.");
    }
}
