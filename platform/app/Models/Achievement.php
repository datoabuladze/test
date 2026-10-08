<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Achievement extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'description'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function periodKey(?\DateTimeInterface $at = null): string
    {
        $at = $at ? Carbon::instance($at) : now();

        return match ($this->period) {
            'daily' => $at->format('Y-m-d'),
            'weekly' => $at->format('o-\WW'),
            'monthly' => $at->format('Y-m'),
            default => 'lifetime',
        };
    }
}
