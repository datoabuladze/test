<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allowed_embed_hosts' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'health_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function allowsEmbedHost(string $host): bool
    {
        $host = strtolower($host);
        foreach ($this->allowed_embed_hosts ?? [] as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === $host || (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }
}
