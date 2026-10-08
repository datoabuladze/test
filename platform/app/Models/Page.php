<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasTranslations;

    public const TYPES = ['page', 'legal', 'blog', 'news', 'faq', 'landing'];

    protected array $translatable = ['title', 'excerpt', 'body', 'seo_title', 'seo_description'];

    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($this->type) {
            'blog', 'news' => route('blog.show', ['locale' => $locale, 'slug' => $this->slug]),
            default => route('pages.show', ['locale' => $locale, 'slug' => $this->slug]),
        };
    }
}
