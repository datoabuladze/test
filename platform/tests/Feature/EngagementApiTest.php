<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rating_and_favorite_require_auth(): void
    {
        $game = Game::factory()->create();
        $this->postJson("/api/games/{$game->slug}/rating", ['stars' => 5])->assertUnauthorized();
        $this->postJson("/api/games/{$game->slug}/favorite")->assertUnauthorized();
        $this->assertDatabaseCount('ratings', 0);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $game = Game::factory()->create();
        $this->actingAs(User::factory()->create());

        foreach ([0, 6, -1, 'five', null, 2.5] as $stars) {
            $this->postJson("/api/games/{$game->slug}/rating", ['stars' => $stars])
                ->assertUnprocessable()->assertJsonValidationErrors('stars');
        }
        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_rating_is_stored_updated_and_aggregated(): void
    {
        $game = Game::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->postJson("/api/games/{$game->slug}/rating", ['stars' => 5])
            ->assertOk()->assertJson(['rating_avg' => 5, 'rating_count' => 1]);
        $this->actingAs($bob)->postJson("/api/games/{$game->slug}/rating", ['stars' => 2])
            ->assertOk()->assertJson(['rating_avg' => 3.5, 'rating_count' => 2]);
        // Re-rating replaces the earlier vote instead of adding one.
        $this->actingAs($alice)->postJson("/api/games/{$game->slug}/rating", ['stars' => 4])
            ->assertOk()->assertJson(['rating_avg' => 3, 'rating_count' => 2]);

        $this->assertDatabaseCount('ratings', 2);
        $this->assertSame(3.0, $game->fresh()->rating_avg);
    }

    public function test_cannot_rate_or_favorite_hidden_games(): void
    {
        $game = Game::factory()->draft()->create();
        $this->actingAs(User::factory()->create());
        $this->postJson("/api/games/{$game->slug}/rating", ['stars' => 5])->assertNotFound();
        $this->postJson("/api/games/{$game->slug}/favorite")->assertNotFound();
    }

    public function test_favorite_toggles(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson("/api/games/{$game->slug}/favorite")->assertOk()->assertJson(['favorited' => true]);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'game_id' => $game->id]);
        $this->assertSame(1, $game->fresh()->favorites_count);

        $this->postJson("/api/games/{$game->slug}/favorite")->assertOk()->assertJson(['favorited' => false]);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'game_id' => $game->id]);
        $this->assertSame(0, $game->fresh()->favorites_count);
    }

    public function test_report_validates_reason(): void
    {
        $game = Game::factory()->create();

        $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'boring'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/games/{$game->slug}/report", [])->assertJsonValidationErrors('reason');
        $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'other', 'message' => str_repeat('x', 1001)])
            ->assertJsonValidationErrors('message');
        $this->assertDatabaseCount('game_reports', 0);
    }

    public function test_guest_can_report_and_ip_is_hashed(): void
    {
        $game = Game::factory()->create();

        $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'not_loading', 'message' => 'Black screen'])
            ->assertCreated()->assertJson(['ok' => true]);

        $report = GameReport::query()->sole();
        $this->assertSame('not_loading', $report->reason);
        $this->assertSame('open', $report->status);
        $this->assertNull($report->user_id);
        $this->assertSame(64, strlen($report->ip_hash));
        $this->assertNotSame('127.0.0.1', $report->ip_hash);
    }

    public function test_report_endpoint_is_throttled(): void
    {
        $game = Game::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'crashes'])->assertCreated();
        }
        $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'crashes'])->assertStatus(429);
        $this->assertDatabaseCount('game_reports', 10);
    }

    public function test_hidden_game_cannot_be_reported(): void
    {
        $game = Game::factory()->unverified()->create();
        $this->postJson("/api/games/{$game->slug}/report", ['reason' => 'crashes'])->assertNotFound();
    }

    public function test_play_start_records_play_and_awards_xp(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson("/api/games/{$game->slug}/plays")->assertOk()->assertJsonStructure(['play_id', 'status_url']);

        $this->assertSame(1, $game->fresh()->play_count);
        $this->assertDatabaseHas('game_plays', ['id' => $res->json('play_id'), 'user_id' => $user->id]);
        $this->assertGreaterThan(0, $user->fresh()->xp);

        // The status URL is signed; tampering with it is rejected.
        $this->postJson($res->json('status_url'), ['status' => 'loaded'])->assertOk();
        $this->postJson('/api/plays/'.$res->json('play_id').'/status', ['status' => 'loaded'])->assertForbidden();
    }
}
