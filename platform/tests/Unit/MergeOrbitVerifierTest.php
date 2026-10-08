<?php

namespace Tests\Unit;

use App\Services\Verifiers\MergeOrbitVerifier;
use App\Services\Verifiers\Mulberry32;
use PHPUnit\Framework\TestCase;

class MergeOrbitVerifierTest extends TestCase
{
    /** Builds a valid move string by greedily choosing moves the verifier accepts. */
    public static function validMoves(int $seed, int $length): string
    {
        $verifier = new MergeOrbitVerifier;
        $moves = '';
        for ($i = 0; $i < $length; $i++) {
            $extended = false;
            foreach (['L', 'D', 'R', 'U'] as $dir) {
                if ($verifier->replay($seed, ['moves' => $moves.$dir]) !== null) {
                    $moves .= $dir;
                    $extended = true;
                    break;
                }
            }
            if (! $extended) {
                break;
            }
        }

        return $moves;
    }

    public function test_mulberry32_is_deterministic_and_in_range(): void
    {
        $a = new Mulberry32(12345);
        $b = new Mulberry32(12345);
        for ($i = 0; $i < 100; $i++) {
            $x = $a->next();
            $this->assertSame($x, $b->next());
            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertLessThan(1, $x);
        }
        $this->assertNotSame((new Mulberry32(1))->next(), (new Mulberry32(2))->next());
    }

    public function test_mulberry32_matches_reference_javascript_output(): void
    {
        // Reference: mulberry32(1)() in JavaScript (the original games' SDK) === 0.6270739405881613
        $this->assertEqualsWithDelta(0.6270739405881613, (new Mulberry32(1))->next(), 1e-15);
    }

    public function test_replay_is_deterministic(): void
    {
        $moves = self::validMoves(42, 30);
        $this->assertGreaterThan(10, strlen($moves));
        $v = new MergeOrbitVerifier;
        $score = $v->replay(42, ['moves' => $moves]);
        $this->assertNotNull($score);
        $this->assertSame($score, $v->replay(42, ['moves' => $moves]));
        $this->assertSame(0, $score % 2);
    }

    public function test_invalid_evidence_is_rejected(): void
    {
        $v = new MergeOrbitVerifier;
        $this->assertNull($v->replay(42, []));
        $this->assertNull($v->replay(42, ['moves' => '']));
        $this->assertNull($v->replay(42, ['moves' => 'LRX']));
        $this->assertNull($v->replay(42, ['moves' => 'lr']));
        $this->assertNull($v->replay(42, ['moves' => str_repeat('L', 100001)]));
    }

    public function test_a_move_that_does_not_change_the_board_invalidates_the_run(): void
    {
        $moves = self::validMoves(7, 5);
        $last = substr($moves, -1);
        // Repeating the same direction many times will eventually be a no-op move.
        $this->assertNull((new MergeOrbitVerifier)->replay(7, ['moves' => $moves.str_repeat($last, 40)]));
    }
}
