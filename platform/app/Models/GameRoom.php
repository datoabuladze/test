<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRoom extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['host_token', 'guest_token'];

    protected $casts = [
        'state' => 'array',
        'host_ready' => 'boolean',
        'guest_ready' => 'boolean',
        'host_seen_at' => 'datetime',
        'guest_seen_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
