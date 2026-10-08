<?php

namespace App\Models;

use App\Enums\HomeSectionType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomepageSection extends Model
{
    use HasTranslations;

    protected array $translatable = ['title'];

    protected $guarded = ['id'];

    protected $casts = ['type' => HomeSectionType::class, 'config' => 'array', 'is_enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('home.sections'));
        static::deleted(fn () => Cache::forget('home.sections'));
    }

    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }
}
