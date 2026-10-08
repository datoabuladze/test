<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Game;
use App\Models\Score;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\XpEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * XP, levels, daily rewards and achievements (lifetime, daily, weekly, monthly).
 * All awards go through the idempotent xp_events ledger.
 */
class Gamification
{
    public const XP_DAILY_FIRST_PLAY = 20;

    public const XP_NEW_GAME_TODAY = 5;

    public function onPlay(User $user, Game $game): void
    {
        $today = now()->toDateString();
        $this->awardXp($user, self::XP_DAILY_FIRST_PLAY, 'daily', $today);
        $this->awardXp($user, self::XP_NEW_GAME_TODAY, 'play', $game->id.':'.$today);
        $this->evaluate($user);
    }

    /** @return bool whether XP was newly awarded */
    public function awardXp(User $user, int $amount, string $reason, string $ref): bool
    {
        $inserted = XpEvent::query()->insertOrIgnore([
            'user_id' => $user->id, 'amount' => $amount, 'reason' => $reason, 'ref' => substr($ref, 0, 64),
            'created_at' => now(),
        ]);
        if (! $inserted) {
            return false;
        }
        $user->xp = (int) XpEvent::query()->where('user_id', $user->id)->sum('amount');
        $user->level = User::levelForXp($user->xp);
        $user->saveQuietly();

        return true;
    }

    /** @return Collection<int, Achievement> newly unlocked achievements */
    public function evaluate(User $user): Collection
    {
        $unlocked = collect();
        $achievements = Achievement::query()->where('is_active', true)->get();
        foreach ($achievements as $achievement) {
            $periodKey = $achievement->periodKey();
            $already = UserAchievement::query()->where('user_id', $user->id)
                ->where('achievement_id', $achievement->id)->where('period_key', $periodKey)->exists();
            if ($already) {
                continue;
            }
            if ($this->progress($user, $achievement) >= $achievement->threshold) {
                $created = UserAchievement::query()->insertOrIgnore([
                    'user_id' => $user->id, 'achievement_id' => $achievement->id,
                    'period_key' => $periodKey, 'unlocked_at' => now(),
                ]);
                if ($created) {
                    $this->awardXp($user, $achievement->xp_reward, 'achievement', $achievement->id.':'.$periodKey);
                    $unlocked->push($achievement);
                }
            }
        }

        return $unlocked;
    }

    public function progress(User $user, Achievement $achievement): int
    {
        [$from, $to] = $this->periodRange($achievement->period);

        $plays = fn () => DB::table('game_plays')->where('user_id', $user->id)
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($achievement->game_id, fn ($q) => $q->where('game_id', $achievement->game_id));

        return match ($achievement->metric) {
            'plays' => (int) $plays()->count(),
            'distinct_games' => (int) $plays()->distinct()->count('game_id'),
            'favorites' => (int) DB::table('favorites')->where('user_id', $user->id)->count(),
            'ratings' => (int) DB::table('ratings')->where('user_id', $user->id)->count(),
            'score' => (int) Score::query()->where('user_id', $user->id)
                ->when($achievement->game_id, fn ($q) => $q->where('game_id', $achievement->game_id))
                ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
                ->max('score'),
            'streak' => $this->streak($user),
            'level' => (int) $user->level,
            default => 0,
        };
    }

    /** Consecutive days (ending today or yesterday) with at least one play. */
    public function streak(User $user): int
    {
        $days = DB::table('game_plays')->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(400))
            ->pluck('created_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->flip();

        $cursor = now()->startOfDay();
        if (! isset($days[$cursor->toDateString()])) {
            $cursor->subDay();
        }
        $streak = 0;
        while (isset($days[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function periodRange(string $period): array
    {
        return match ($period) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [null, null],
        };
    }
}
