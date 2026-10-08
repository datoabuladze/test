<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Game;
use App\Models\User;
use App\Services\Gamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_xp_is_awarded_once_per_reason_and_ref(): void
    {
        $user = User::factory()->create();
        $g = app(Gamification::class);

        $this->assertTrue($g->awardXp($user, 60, 'bonus', 'welcome'));
        $this->assertFalse($g->awardXp($user, 60, 'bonus', 'welcome'));
        $this->assertTrue($g->awardXp($user, 50, 'bonus', 'second'));
        $this->assertTrue($g->awardXp($user, 10, 'other', 'welcome'));

        $user->refresh();
        $this->assertSame(120, $user->xp);
        $this->assertSame(2, $user->level);
        $this->assertDatabaseCount('xp_events', 3);
    }

    public function test_awards_are_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $g = app(Gamification::class);

        $this->assertTrue($g->awardXp($a, 10, 'daily', '2026-10-08'));
        $this->assertTrue($g->awardXp($b, 10, 'daily', '2026-10-08'));
        $this->assertSame(10, $a->fresh()->xp);
        $this->assertSame(10, $b->fresh()->xp);
    }

    public function test_daily_play_xp_is_idempotent_within_a_day(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $g = app(Gamification::class);

        $g->onPlay($user, $game);
        $g->onPlay($user, $game);
        $this->assertSame(Gamification::XP_DAILY_FIRST_PLAY + Gamification::XP_NEW_GAME_TODAY, $user->fresh()->xp);

        $g->onPlay($user, Game::factory()->create());
        $this->assertSame(Gamification::XP_DAILY_FIRST_PLAY + 2 * Gamification::XP_NEW_GAME_TODAY, $user->fresh()->xp);

        $this->travel(1)->days();
        $g->onPlay($user, $game);
        $this->assertSame(2 * Gamification::XP_DAILY_FIRST_PLAY + 3 * Gamification::XP_NEW_GAME_TODAY, $user->fresh()->xp);
    }

    public function test_level_is_recomputed_from_xp(): void
    {
        $user = User::factory()->create();
        app(Gamification::class)->awardXp($user, User::xpForLevel(5), 'grant', 'x');
        $this->assertSame(5, $user->fresh()->level);
        $this->assertEqualsWithDelta(0.0, $user->fresh()->levelProgress(), 0.0001);
    }

    public function test_achievement_unlocks_once_and_awards_its_xp(): void
    {
        $user = User::factory()->create();
        $achievement = Achievement::query()->create([
            'key' => 'first-fav', 'name' => ['en' => 'Collector'], 'description' => ['en' => 'Favorite a game'],
            'period' => 'lifetime', 'metric' => 'favorites', 'threshold' => 1, 'xp_reward' => 30, 'is_active' => true,
        ]);
        $game = Game::factory()->create();
        $g = app(Gamification::class);

        $this->assertCount(0, $g->evaluate($user));
        $user->favorites()->attach($game->id);

        $this->assertSame([$achievement->id], $g->evaluate($user)->pluck('id')->all());
        $this->assertCount(0, $g->evaluate($user));
        $this->assertSame(30, $user->fresh()->xp);
        $this->assertDatabaseCount('user_achievements', 1);
    }
}
