<?php

namespace App\Models;

use App\Enums\FlashCompatibility;
use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Game extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected array $translatable = [
        'title', 'short_description', 'description', 'instructions', 'controls', 'seo_title', 'seo_description',
    ];

    protected $guarded = ['id', 'play_count', 'favorites_count', 'rating_count', 'rating_avg', 'search_text'];

    protected function casts(): array
    {
        return [
            'engine' => GameEngine::class,
            'status' => GameStatus::class,
            'rights_status' => RightsStatus::class,
            'flash_compatibility' => FlashCompatibility::class,
            'engine_config' => 'array',
            'input_types' => 'array',
            'devices' => 'array',
            'languages' => 'array',
            'published_at' => 'datetime',
            'rights_verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'commercial_use_allowed' => 'boolean',
            'ads_allowed' => 'boolean',
            'modifications_allowed' => 'boolean',
            'thumbnail_rights' => 'boolean',
            'embed_authorized' => 'boolean',
            'is_featured' => 'boolean',
            'is_editors_pick' => 'boolean',
            'is_multiplayer' => 'boolean',
            'is_mobile_friendly' => 'boolean',
            'is_original' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Game $game) {
            if (! $game->slug) {
                $game->slug = Str::slug($game->tr('title', 'en')) ?: Str::random(8);
            }
            $game->search_text = $game->buildSearchText();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- relations

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function plays(): HasMany
    {
        return $this->hasMany(GamePlay::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(GameReport::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }

    // ---------------------------------------------------------------- scopes

    /**
     * Games visitors may see: published, rights verified, release time reached,
     * not known-broken, and (for Flash) verified compatible with Ruffle.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('status', GameStatus::Published->value)
            ->where('rights_status', RightsStatus::Verified->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('launch_status', '!=', 'failed')
            ->where(function (Builder $q) {
                $q->where('engine', '!=', GameEngine::Ruffle->value)
                    ->orWhereIn('flash_compatibility', [
                        FlashCompatibility::Compatible->value, FlashCompatibility::Partial->value,
                    ]);
            });
    }

    public function scopeInCategory(Builder $query, Category $category): Builder
    {
        $ids = $category->descendantAndSelfIds();

        return $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $ids));
    }

    public function scopeForCard(Builder $query): Builder
    {
        return $query->select([
            'id', 'slug', 'title', 'short_description', 'thumbnail_path', 'thumbnail_color', 'engine',
            'is_multiplayer', 'is_mobile_friendly', 'is_original', 'rating_avg', 'rating_count', 'play_count',
            'published_at', 'devices', 'input_types',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    public function isPublic(): bool
    {
        return static::query()->public()->whereKey($this->getKey())->exists();
    }

    public function isNew(): bool
    {
        return $this->published_at !== null && $this->published_at->gt(now()->subDays(14));
    }

    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }
        if (Str::startsWith($this->thumbnail_path, ['http://', 'https://', '/'])) {
            return $this->thumbnail_path;
        }

        return asset('storage/'.$this->thumbnail_path);
    }

    public function url(?string $locale = null): string
    {
        return route('games.show', ['locale' => $locale ?? app()->getLocale(), 'game' => $this->slug]);
    }

    public function supportsDevice(string $device): bool
    {
        return empty($this->devices) || in_array($device, $this->devices, true);
    }

    public function requiresKeyboard(): bool
    {
        $inputs = $this->input_types ?? [];

        return in_array('keyboard', $inputs, true) && ! in_array('touch', $inputs, true);
    }

    public function primaryCategory(): ?Category
    {
        return $this->categories->firstWhere('pivot.is_primary', true) ?? $this->categories->first();
    }

    public function buildSearchText(): string
    {
        $parts = [];
        foreach (['title', 'short_description'] as $attr) {
            $value = $this->getAttribute($attr);
            if (is_array($value)) {
                $parts = array_merge($parts, array_values($value));
            }
        }
        $parts[] = $this->developer;
        $parts[] = $this->slug ? str_replace('-', ' ', $this->slug) : null;
        if ($this->exists) {
            foreach ($this->tags()->get() as $tag) {
                $parts = array_merge($parts, array_values($tag->name ?? []));
            }
            foreach ($this->categories()->get() as $category) {
                $parts = array_merge($parts, array_values($category->name ?? []));
            }
            if ($this->provider_id) {
                $parts[] = $this->provider?->name;
            }
        }

        return Str::lower(implode(' ', array_filter($parts)));
    }

    public function refreshSearchText(): void
    {
        $this->forceFill(['search_text' => $this->buildSearchText()])->saveQuietly();
    }
}
