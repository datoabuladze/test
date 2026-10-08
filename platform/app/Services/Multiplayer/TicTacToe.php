<?php

namespace App\Services\Multiplayer;

class TicTacToe implements Rules
{
    private const LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];

    public function initialState(int $firstPlayer): array
    {
        return ['board' => array_fill(0, 9, null), 'turn' => $firstPlayer, 'first' => $firstPlayer,
            'winner' => null, 'draw' => false, 'line' => null, 'moves' => 0];
    }

    public function apply(array $state, int $player, array $move): array
    {
        if ($state['winner'] !== null || $state['draw']) {
            throw new InvalidMove('The game is over.');
        }
        if ($state['turn'] !== $player) {
            throw new InvalidMove('Not your turn.');
        }
        $cell = $move['cell'] ?? null;
        if (! is_int($cell) || $cell < 0 || $cell > 8) {
            throw new InvalidMove('Invalid cell.');
        }
        if ($state['board'][$cell] !== null) {
            throw new InvalidMove('Cell already taken.');
        }
        $state['board'][$cell] = $player;
        $state['moves']++;
        foreach (self::LINES as $line) {
            [$a, $b, $c] = $line;
            if ($state['board'][$a] === $player && $state['board'][$b] === $player && $state['board'][$c] === $player) {
                $state['winner'] = $player;
                $state['line'] = $line;

                return $state;
            }
        }
        if ($state['moves'] === 9) {
            $state['draw'] = true;

            return $state;
        }
        $state['turn'] = 1 - $player;

        return $state;
    }
}
