<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'nickname', 'email', 'password', 'locale', 'profile_public', 'personalization_enabled',
        'notification_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'profile_public' => 'boolean',
            'personalization_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'suspended_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'favorites')->withPivot('created_at')->orderByPivot('created_at', 'desc');
    }

    public function plays(): HasMany
    {
        return $this->hasMany(GamePlay::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function xpEvents(): HasMany
    {
        return $this->hasMany(XpEvent::class);
    }

    public function hasPermission(string $permission): bool
    {
        return ! $this->isSuspended() && ($this->role ?? Role::Player)->can($permission);
    }

    public function isStaff(): bool
    {
        return $this->hasPermission('admin.access');
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->nickname ?: $this->name, 0, 1));
    }

    /** XP needed to reach a given level: 100 * (level-1)^1.5, rounded. */
    public static function xpForLevel(int $level): int
    {
        return (int) round(100 * (max(1, $level) - 1) ** 1.5);
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;
        while (self::xpForLevel($level + 1) <= $xp && $level < 999) {
            $level++;
        }

        return $level;
    }

    public function levelProgress(): float
    {
        $current = self::xpForLevel($this->level);
        $next = self::xpForLevel($this->level + 1);

        return $next > $current ? min(1, max(0, ($this->xp - $current) / ($next - $current))) : 1;
    }
}
