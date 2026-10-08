<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Score;
use App\Models\User;
use App\Services\Verifiers\MergeOrbitVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\MergeOrbitVerifierTest;

class ScoreSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->user = User::factory()->create();
        $this->game = Game::factory()->create(['score_mode' => 'casual', 'engine_config' => ['max_points_per_second' => 100]]);
    }

    private function openScoreSession(?Game $game = null): array
    {
        $game ??= $this->game;

        return $this->actingAs($this->user)->postJson("/api/games/{$game->slug}/score-session")
            ->assertOk()->assertJsonStructure(['token', 'seed'])->json();
    }

    private function submit(array $payload, ?Game $game = null)
    {
        $game ??= $this->game;

        return $this->actingAs($this->user)->postJson("/api/games/{$game->slug}/scores", $payload);
    }

    public function test_guests_cannot_start_sessions_or_submit(): void
    {
        $this->postJson("/api/games/{$this->game->slug}/score-session")->assertUnauthorized();
        $this->postJson("/api/games/{$this->game->slug}/scores", ['token' => Str::random(48), 'score' => 1, 'duration_ms' => 1])
            ->assertUnauthorized();
        $this->assertSame(0, Score::query()->count());
    }

    public function test_plausible_score_is_saved_as_casual(): void
    {
        $session = $this->openScoreSession();
        $this->travel(30)->seconds();

        $this->submit(['token' => $session['token'], 'score' => 1500, 'duration_ms' => 29000])
            ->assertCreated()->assertJson(['verified' => false]);

        $score = Score::query()->sole();
        $this->assertSame(1500, $score->score);
        $this->assertSame('plausibility', $score->verification);
        $this->assertFalse($score->is_verified);
        $this->assertSame($this->user->id, $score->user_id);
    }

    public function test_session_token_is_single_use(): void
    {
        $session = $this->openScoreSession();
        $this->travel(10)->seconds();

        $this->submit(['token' => $session['token'], 'score' => 100, 'duration_ms' => 9000])->assertCreated();
        $this->submit(['token' => $session['token'], 'score' => 100, 'duration_ms' => 9000])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertSame(1, Score::query()->count());
    }

    public function test_score_without_a_valid_session_is_rejected(): void
    {
        $this->submit(['score' => 10, 'duration_ms' => 5000])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->submit(['token' => Str::random(48), 'score' => 10, 'duration_ms' => 5000])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame(0, Score::query()->count());
    }

    public function test_session_of_another_user_or_game_is_rejected(): void
    {
        $session = $this->openScoreSession();
        $this->travel(10)->seconds();

        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/games/{$this->game->slug}/scores", ['token' => $session['token'], 'score' => 10, 'duration_ms' => 5000])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $otherGame = Game::factory()->create(['score_mode' => 'casual']);
        $this->submit(['token' => $session['token'], 'score' => 10, 'duration_ms' => 5000], $otherGame)
            ->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_expired_session_is_rejected(): void
    {
        $session = $this->openScoreSession();
        $this->travel(181)->minutes();

        $this->submit(['token' => $session['token'], 'score' => 10, 'duration_ms' => 5000])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_score_exceeding_points_per_second_is_rejected(): void
    {
        $session = $this->openScoreSession();
        $this->travel(10)->seconds();

        // 100 points/second allowed => at most 1000 points in 10 seconds.
        $this->submit(['token' => $session['token'], 'score' => 5000, 'duration_ms' => 10000])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
        $this->assertSame(0, Score::query()->count());
    }

    public function test_claimed_duration_longer_than_real_elapsed_time_is_rejected(): void
    {
        $session = $this->openScoreSession();
        $this->travel(2)->seconds();

        // Claims a 60s run to justify a big score, but only 2s really passed.
        $this->submit(['token' => $session['token'], 'score' => 5000, 'duration_ms' => 60000])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
    }

    public function test_score_above_game_maximum_is_rejected(): void
    {
        $this->game->update(['engine_config' => ['max_points_per_second' => 1000, 'max_score' => 500]]);
        $session = $this->openScoreSession();
        $this->travel(60)->seconds();

        $this->submit(['token' => $session['token'], 'score' => 501, 'duration_ms' => 59000])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
    }

    public function test_rejected_submission_consumes_the_token(): void
    {
        $session = $this->openScoreSession();
        $this->travel(10)->seconds();
        $this->submit(['token' => $session['token'], 'score' => 999999, 'duration_ms' => 10000])->assertUnprocessable();

        // The token is burned, so it can't be reused to probe the plausibility limit.
        $this->submit(['token' => $session['token'], 'score' => 100, 'duration_ms' => 10000])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_games_without_scores_or_hidden_games_404(): void
    {
        $none = Game::factory()->create(['score_mode' => 'none']);
        $this->actingAs($this->user)->postJson("/api/games/{$none->slug}/score-session")->assertNotFound();

        $hidden = Game::factory()->draft()->create(['score_mode' => 'casual']);
        $this->actingAs($this->user)->postJson("/api/games/{$hidden->slug}/score-session")->assertNotFound();
    }

    private function mergeOrbit(): Game
    {
        return Game::factory()->original()->create([
            'score_mode' => 'verified',
            'engine_config' => ['verifier' => MergeOrbitVerifier::class, 'max_points_per_second' => 1000],
        ]);
    }

    public function test_merge_orbit_valid_replay_is_verified(): void
    {
        $game = $this->mergeOrbit();
        $session = $this->openScoreSession($game);
        $moves = MergeOrbitVerifierTest::validMoves((int) $session['seed'], 40);
        $expected = (new MergeOrbitVerifier)->replay((int) $session['seed'], ['moves' => $moves]);
        $this->assertNotNull($expected);
        $this->travel(60)->seconds();

        $this->submit(['token' => $session['token'], 'score' => $expected, 'duration_ms' => 59000, 'evidence' => ['moves' => $moves]], $game)
            ->assertCreated()->assertJson(['verified' => true]);

        $score = Score::query()->sole();
        $this->assertTrue($score->is_verified);
        $this->assertSame('replay', $score->verification);
        $this->assertSame(['moves' => strlen($moves)], $score->evidence);
    }

    public function test_merge_orbit_inflated_score_is_not_verified(): void
    {
        $game = $this->mergeOrbit();
        $session = $this->openScoreSession($game);
        $moves = MergeOrbitVerifierTest::validMoves((int) $session['seed'], 40);
        $real = (new MergeOrbitVerifier)->replay((int) $session['seed'], ['moves' => $moves]);
        $this->travel(60)->seconds();

        $this->submit(['token' => $session['token'], 'score' => $real + 4, 'duration_ms' => 59000, 'evidence' => ['moves' => $moves]], $game)
            ->assertCreated()->assertJson(['verified' => false]);
        $this->assertSame('plausibility', Score::query()->sole()->verification);
    }

    public function test_merge_orbit_tampered_evidence_is_not_verified(): void
    {
        $game = $this->mergeOrbit();
        $session = $this->openScoreSession($game);
        $moves = MergeOrbitVerifierTest::validMoves((int) $session['seed'], 40);
        $real = (new MergeOrbitVerifier)->replay((int) $session['seed'], ['moves' => $moves]);
        $this->travel(60)->seconds();

        // A doctored move list (here: an injected non-move symbol) fails the replay, so the
        // claimed score is only accepted as an unverified casual score.
        $this->submit(['token' => $session['token'], 'score' => $real, 'duration_ms' => 59000, 'evidence' => ['moves' => $moves.'Q']], $game)
            ->assertCreated()->assertJson(['verified' => false]);
        $this->assertFalse(Score::query()->sole()->is_verified);
    }

    public function test_merge_orbit_without_evidence_is_casual(): void
    {
        $game = $this->mergeOrbit();
        $session = $this->openScoreSession($game);
        $this->travel(60)->seconds();

        $this->submit(['token' => $session['token'], 'score' => 64, 'duration_ms' => 59000], $game)
            ->assertCreated()->assertJson(['verified' => false]);
    }
}
