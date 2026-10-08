<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ThemeVersion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['tokens' => 'array', 'is_active' => 'boolean'];

    public const DEFAULTS = [
        'brand_primary' => '#7c5cff',
        'brand_secondary' => '#22d3ee',
        'brand_accent' => '#f472b6',
        'surface_dark' => '#0b0d17',
        'font_display' => 'Outfit Variable',
        'font_body' => 'Inter Variable',
        'radius' => 'lg',          // sm|md|lg|xl
        'card_style' => 'glow',    // flat|glow|outline
        'density' => 'comfortable', // compact|comfortable|spacious
        'default_mode' => 'dark',  // dark|light|system
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('theme.active'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    public static function activeTokens(): array
    {
        return Cache::rememberForever('theme.active', function () {
            $active = static::query()->where('is_active', true)->latest('id')->first();

            return array_merge(self::DEFAULTS, $active?->tokens ?? []);
        });
    }
}
