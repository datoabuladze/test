<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPlacement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }
}
