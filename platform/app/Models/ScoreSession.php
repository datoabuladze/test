<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreSession extends Model
{
    protected $table = 'score_sessions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['started_at' => 'datetime', 'used_at' => 'datetime'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
