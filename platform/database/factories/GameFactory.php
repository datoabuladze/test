<?php

namespace Database\Factories;

use App\Enums\FlashCompatibility;
use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Game;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default state: a self-hosted HTML5 game that passes every publication gate
 * (published, rights verified, released, launch ok) and is therefore public.
 *
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $word = Str::lower(Str::random(10));

        return [
            'slug' => 'game-'.$word,
            'title' => ['en' => 'Game '.$word],
            'short_description' => ['en' => 'A test game.'],
            'engine' => GameEngine::Html5,
            'entry_path' => 'game-files/test-'.$word.'/index.html',
            'orientation' => 'any',
            'source_url' => 'https://example.org/source/'.$word,
            'license_type' => 'MIT',
            'license_url' => 'https://opensource.org/licenses/MIT',
            'hosting_method' => 'self_hosted',
            'commercial_use_allowed' => true,
            'ads_allowed' => true,
            'modifications_allowed' => true,
            'thumbnail_rights' => true,
            'embed_authorized' => false,
            'rights_status' => RightsStatus::Verified,
            'rights_verified_at' => now()->subDay(),
            'status' => GameStatus::Published,
            'published_at' => now()->subHour(),
            'launch_status' => 'ok',
            'score_mode' => 'none',
            'is_original' => false,
        ];
    }

    public function title(string $en, array $other = []): static
    {
        return $this->state(fn () => ['title' => ['en' => $en] + $other, 'slug' => Str::slug($en).'-'.Str::lower(Str::random(4))]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => GameStatus::Draft, 'published_at' => null]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['rights_status' => RightsStatus::Unverified, 'rights_verified_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDays(3)]);
    }

    public function original(): static
    {
        return $this->state(fn (array $a) => [
            'engine' => GameEngine::Original,
            'entry_path' => 'games/originals/'.$a['slug'].'/index.html',
            'is_original' => true,
            'source_url' => null,
            'license_type' => 'Original work',
        ]);
    }

    public function ruffle(FlashCompatibility $compat = FlashCompatibility::Compatible): static
    {
        return $this->state(fn (array $a) => [
            'engine' => GameEngine::Ruffle,
            'entry_path' => 'game-files/'.$a['slug'].'/game.swf',
            'flash_compatibility' => $compat,
        ]);
    }

    public function iframe(string $url = 'https://games.example.com/play/1'): static
    {
        return $this->state(fn () => [
            'engine' => GameEngine::Iframe,
            'entry_path' => null,
            'embed_url' => $url,
            'embed_authorized' => true,
            'hosting_method' => 'iframe_embed',
            'provider_id' => Provider::factory(),
        ]);
    }
}
