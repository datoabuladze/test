<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        $slug = 'prov-'.Str::lower(Str::random(8));

        return [
            'slug' => $slug,
            'name' => 'Provider '.Str::upper(Str::random(4)),
            'adapter' => 'manual',
            'allowed_embed_hosts' => ['games.example.com', '*.cdn.example.net'],
            'settings' => [],
            'is_active' => true,
        ];
    }

    /** Provider whose settings supply licensing defaults for imports. */
    public function withLicenseDefaults(): static
    {
        return $this->state(fn () => ['settings' => [
            'license_type' => 'Provider agreement',
            'source_url' => 'https://games.example.com/catalog',
            'hosting_method' => 'iframe_embed',
            'embed_authorized' => '1',
        ]]);
    }
}
