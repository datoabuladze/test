<?php

namespace App\Services\Multiplayer;

/** Server-authoritative rules for a two-player turn-based game. Player index: 0 = host, 1 = guest. */
interface Rules
{
    public function initialState(int $firstPlayer): array;

    /** @throws InvalidMove */
    public function apply(array $state, int $player, array $move): array;
}
