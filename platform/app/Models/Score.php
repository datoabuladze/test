<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $table = 'scores';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected $casts = ['created_at' => 'datetime', 'evidence' => 'array', 'is_verified' => 'boolean'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
