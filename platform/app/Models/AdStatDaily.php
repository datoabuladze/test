<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdStatDaily extends Model
{
    protected $table = 'ad_stats_daily';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['date' => 'date', 'revenue' => 'decimal:2'];
}
