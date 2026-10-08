<?php

namespace App\Services\Multiplayer;

class ConnectFour implements Rules
{
    public const COLS = 7;

    public const ROWS = 6;

    public function initialState(int $firstPlayer): array
    {
        // board[row][col], row 0 = top
        return ['board' => array_fill(0, self::ROWS, array_fill(0, self::COLS, null)), 'turn' => $firstPlayer,
            'first' => $firstPlayer, 'winner' => null, 'draw' => false, 'line' => null, 'moves' => 0, 'last' => null];
    }

    public function apply(array $state, int $player, array $move): array
    {
        if ($state['winner'] !== null || $state['draw']) {
            throw new InvalidMove('The game is over.');
        }
        if ($state['turn'] !== $player) {
            throw new InvalidMove('Not your turn.');
        }
        $col = $move['col'] ?? null;
        if (! is_int($col) || $col < 0 || $col >= self::COLS) {
            throw new InvalidMove('Invalid column.');
        }
        $row = null;
        for ($r = self::ROWS - 1; $r >= 0; $r--) {
            if ($state['board'][$r][$col] === null) {
                $row = $r;
                break;
            }
        }
        if ($row === null) {
            throw new InvalidMove('Column is full.');
        }
        $state['board'][$row][$col] = $player;
        $state['moves']++;
        $state['last'] = [$row, $col];

        if ($line = $this->winningLine($state['board'], $row, $col, $player)) {
            $state['winner'] = $player;
            $state['line'] = $line;

            return $state;
        }
        if ($state['moves'] === self::ROWS * self::COLS) {
            $state['draw'] = true;

            return $state;
        }
        $state['turn'] = 1 - $player;

        return $state;
    }

    /** @return list<array{int,int}>|null */
    private function winningLine(array $board, int $row, int $col, int $player): ?array
    {
        foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
            $cells = [[$row, $col]];
            foreach ([1, -1] as $sign) {
                $r = $row + $dr * $sign;
                $c = $col + $dc * $sign;
                while ($r >= 0 && $r < self::ROWS && $c >= 0 && $c < self::COLS && $board[$r][$c] === $player) {
                    $cells[] = [$r, $c];
                    $r += $dr * $sign;
                    $c += $dc * $sign;
                }
            }
            if (count($cells) >= 4) {
                return $cells;
            }
        }

        return null;
    }
}
