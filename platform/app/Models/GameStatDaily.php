<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStatDaily extends Model
{
    protected $table = 'game_stats_daily';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['date' => 'date'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
