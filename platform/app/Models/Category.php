<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['name', 'description', 'seo_title', 'seo_description'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
            'show_on_home' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('categories.tree'));
        static::deleted(fn () => Cache::forget('categories.tree'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class)->withPivot('is_primary');
    }

    /** @return list<int> */
    public function descendantAndSelfIds(): array
    {
        $all = Category::query()->get(['id', 'parent_id']);
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $next = $all->whereIn('parent_id', $frontier)->pluck('id')->all();
            $next = array_values(array_diff($next, $ids));
            $ids = array_merge($ids, $next);
            $frontier = $next;
        }

        return $ids;
    }

    /** @return list<Category> root-first chain for breadcrumbs */
    public function ancestry(): array
    {
        $chain = [$this];
        $node = $this;
        $guard = 0;
        while ($node->parent_id && $guard++ < 10) {
            $node = $node->parent;
            if (! $node) {
                break;
            }
            array_unshift($chain, $node);
        }

        return $chain;
    }

    public function url(?string $locale = null): string
    {
        return route('categories.show', ['locale' => $locale ?? app()->getLocale(), 'category' => $this->slug]);
    }
}
