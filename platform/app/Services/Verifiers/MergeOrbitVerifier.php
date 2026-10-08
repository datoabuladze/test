<?php

namespace App\Services\Verifiers;

/**
 * Server-side replay of Merge Orbit (public/games/originals/merge-orbit/game.js).
 * Spawns use the same mulberry32 sequence from the session seed, so replaying the
 * recorded moves reproduces the exact board and score.
 */
class MergeOrbitVerifier implements ScoreVerifier
{
    private const N = 4;

    public function replay(int $seed, array $evidence): ?int
    {
        $moves = (string) ($evidence['moves'] ?? '');
        if ($moves === '' || ! preg_match('/^[LRUD]+$/', $moves) || strlen($moves) > 100000) {
            return null;
        }
        $rng = new Mulberry32($seed);
        $grid = array_fill(0, self::N * self::N, 0);
        $this->spawn($grid, $rng);
        $this->spawn($grid, $rng);
        $score = 0;

        foreach (str_split($moves) as $dir) {
            [$moved, $gained] = $this->slide($grid, $dir);
            if (! $moved) {
                return null; // the client only records moves that changed the board
            }
            $score += $gained;
            $this->spawn($grid, $rng);
        }

        return $score;
    }

    private function spawn(array &$grid, Mulberry32 $rng): void
    {
        $empty = [];
        foreach ($grid as $i => $v) {
            if ($v === 0) {
                $empty[] = $i;
            }
        }
        if (! $empty) {
            return;
        }
        $idx = $empty[(int) floor($rng->next() * count($empty))];
        $grid[$idx] = $rng->next() < 0.9 ? 2 : 4;
    }

    /** @return array{0: bool, 1: int} */
    private function slide(array &$grid, string $dir): array
    {
        $n = self::N;
        $moved = false;
        $gained = 0;
        for ($line = 0; $line < $n; $line++) {
            $idxs = [];
            for ($k = 0; $k < $n; $k++) {
                [$r, $c] = match ($dir) {
                    'L' => [$line, $k], 'R' => [$line, $n - 1 - $k], 'U' => [$k, $line], default => [$n - 1 - $k, $line],
                };
                $idxs[] = $r * $n + $c;
            }
            $tiles = array_values(array_filter(array_map(fn ($i) => $grid[$i], $idxs)));
            $out = [];
            for ($t = 0; $t < count($tiles); $t++) {
                if ($t + 1 < count($tiles) && $tiles[$t] === $tiles[$t + 1]) {
                    $out[] = $tiles[$t] * 2;
                    $gained += $tiles[$t] * 2;
                    $t++;
                } else {
                    $out[] = $tiles[$t];
                }
            }
            foreach ($idxs as $p => $i) {
                $after = $out[$p] ?? 0;
                if ($grid[$i] !== $after) {
                    $moved = true;
                }
                $grid[$i] = $after;
            }
        }

        return [$moved, $gained];
    }
}
