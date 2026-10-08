<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Score;
use App\Models\ScoreSession;
use App\Models\User;
use App\Services\Verifiers\ScoreVerifier;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Score submission with layered anti-cheat:
 *  1. Single-use session token issued server-side at game start (binds user+game+seed+start time).
 *  2. Plausibility checks: elapsed time vs. server clock, max points per second, upper bounds.
 *  3. Deterministic replay verification for games that provide a verifier: the server
 *     re-simulates the run from the seed and the recorded inputs. Only replay-verified
 *     scores enter "verified" leaderboards; everything else is labelled casual.
 */
class ScoreService
{
    public const SESSION_TTL_MINUTES = 180;

    public function startSession(User $user, Game $game): ScoreSession
    {
        ScoreSession::query()->where('user_id', $user->id)->where('started_at', '<', now()->subMinutes(self::SESSION_TTL_MINUTES))->delete();

        return ScoreSession::query()->create([
            'token' => Str::random(48),
            'game_id' => $game->id,
            'user_id' => $user->id,
            'seed' => random_int(1, 2_147_483_646),
            'started_at' => now(),
        ]);
    }

    public function submit(User $user, Game $game, string $token, int $score, int $durationMs, ?array $evidence): Score
    {
        /** @var ScoreSession|null $session */
        $session = ScoreSession::query()->where('token', $token)->lockForUpdate()->first();
        if (! $session || $session->user_id !== $user->id || $session->game_id !== $game->id) {
            throw ValidationException::withMessages(['token' => __('Invalid score session.')]);
        }
        if ($session->used_at) {
            throw ValidationException::withMessages(['token' => __('This score was already submitted.')]);
        }
        if ($session->started_at->lt(now()->subMinutes(self::SESSION_TTL_MINUTES))) {
            throw ValidationException::withMessages(['token' => __('Score session expired.')]);
        }
        $session->forceFill(['used_at' => now()])->save();

        $elapsedMs = (int) $session->started_at->diffInMilliseconds(now());
        $cfg = $game->engine_config ?? [];
        $maxRate = (float) ($cfg['max_points_per_second'] ?? config('platform.scores.max_points_per_second_default'));
        $maxScore = (int) ($cfg['max_score'] ?? PHP_INT_MAX);

        $plausible = $score >= 0
            && $score <= $maxScore
            && $durationMs <= $elapsedMs + 5000          // client can't claim more time than really passed
            && $score <= max(50, $maxRate * max(1, $durationMs) / 1000);
        if (! $plausible) {
            throw ValidationException::withMessages(['score' => __('This score could not be accepted.')]);
        }

        $verification = 'plausibility';
        $verified = false;
        $verifierClass = $cfg['verifier'] ?? null;
        if ($game->score_mode === 'verified' && $verifierClass && is_a($verifierClass, ScoreVerifier::class, true) && $evidence) {
            /** @var ScoreVerifier $verifier */
            $verifier = app($verifierClass);
            $replayed = $verifier->replay((int) $session->seed, $evidence);
            if ($replayed !== null && $replayed === $score) {
                $verified = true;
                $verification = 'replay';
            }
        }

        $record = Score::query()->create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'score' => $score,
            'is_verified' => $verified,
            'verification' => $verification,
            'duration_ms' => $durationMs,
            'evidence' => $evidence ? ['moves' => mb_strlen((string) ($evidence['moves'] ?? ''))] : null,
        ]);
        app(Gamification::class)->evaluate($user);

        return $record;
    }
}
