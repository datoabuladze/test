<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use HasTranslations;

    protected array $translatable = ['label'];

    protected $guarded = ['id'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function href(): string
    {
        if (Str::startsWith($this->url, ['http://', 'https://'])) {
            return $this->url;
        }

        return url(app()->getLocale().'/'.ltrim($this->url, '/'));
    }

    public function isExternal(): bool
    {
        return Str::startsWith($this->url, ['http://', 'https://']);
    }
}
