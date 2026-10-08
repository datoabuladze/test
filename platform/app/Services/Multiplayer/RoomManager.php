<?php

namespace App\Services\Multiplayer;

use App\Models\GameRoom;
use Illuminate\Support\Str;

class RoomManager
{
    public const GAMES = ['tictactoe' => TicTacToe::class, 'connect4' => ConnectFour::class];

    public const TTL_MINUTES = 120;

    public const PRESENCE_SECONDS = 20;

    public function rules(string $game): Rules
    {
        return app(self::GAMES[$game] ?? throw new InvalidMove('Unknown game.'));
    }

    public function create(string $game, ?int $userId): GameRoom
    {
        $this->rules($game);
        do {
            $code = Str::upper(Str::random(6));
        } while (GameRoom::query()->where('code', $code)->exists() || preg_match('/[O0I1]/', $code));

        return GameRoom::query()->create([
            'code' => $code,
            'game' => $game,
            'status' => 'waiting',
            'state' => ['round' => 0, 'score' => [0, 0]],
            'host_token' => Str::random(48),
            'host_user_id' => $userId,
            'host_seen_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    /** @return int|null 0 host, 1 guest, null spectator */
    public function seat(GameRoom $room, ?string $token): ?int
    {
        if (! $token) {
            return null;
        }
        if (hash_equals($room->host_token, $token)) {
            return 0;
        }
        if ($room->guest_token && hash_equals($room->guest_token, $token)) {
            return 1;
        }

        return null;
    }

    public function join(GameRoom $room, ?int $userId): string
    {
        if ($room->guest_token) {
            throw new InvalidMove('This room is full.');
        }
        $token = Str::random(48);
        $room->forceFill(['guest_token' => $token, 'guest_user_id' => $userId, 'guest_seen_at' => now()])->save();

        return $token;
    }

    public function touch(GameRoom $room, ?int $seat): void
    {
        if ($seat === null) {
            return;
        }
        $room->forceFill([$seat === 0 ? 'host_seen_at' : 'guest_seen_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)])->saveQuietly();
    }

    public function ready(GameRoom $room, int $seat): void
    {
        $room->forceFill([$seat === 0 ? 'host_ready' : 'guest_ready' => true]);
        if ($room->host_ready && $room->guest_ready && $room->status === 'waiting') {
            $this->startRound($room);
        }
        $room->version++;
        $room->save();
    }

    public function move(GameRoom $room, int $seat, array $move): void
    {
        if ($room->status !== 'playing') {
            throw new InvalidMove('The game has not started.');
        }
        $state = $room->state;
        $state['game'] = $this->rules($room->game)->apply($state['game'], $seat, $move);
        if ($state['game']['winner'] !== null || $state['game']['draw']) {
            if ($state['game']['winner'] !== null) {
                $state['score'][$state['game']['winner']]++;
            }
            $room->status = 'finished';
        }
        $room->state = $state;
        $room->version++;
        $room->save();
    }

    public function rematch(GameRoom $room, int $seat): void
    {
        if ($room->status !== 'finished') {
            throw new InvalidMove('The round is still running.');
        }
        $this->startRound($room);
        $room->version++;
        $room->save();
    }

    private function startRound(GameRoom $room): void
    {
        $state = $room->state;
        $state['round'] = ($state['round'] ?? 0) + 1;
        // Alternate who moves first each round.
        $first = ($state['round'] + 1) % 2;
        $state['game'] = $this->rules($room->game)->initialState($first);
        $room->state = $state;
        $room->status = 'playing';
    }

    public function isPresent(?\DateTimeInterface $seenAt): bool
    {
        return $seenAt !== null && now()->diffInSeconds($seenAt, true) <= self::PRESENCE_SECONDS;
    }

    public function payload(GameRoom $room, ?int $seat): array
    {
        return [
            'code' => $room->code,
            'game' => $room->game,
            'status' => $room->status,
            'version' => $room->version,
            'you' => $seat,
            'state' => $room->state,
            'host' => ['present' => $this->isPresent($room->host_seen_at), 'ready' => $room->host_ready],
            'guest' => ['joined' => (bool) $room->guest_token, 'present' => $this->isPresent($room->guest_seen_at), 'ready' => $room->guest_ready],
            'expires_at' => $room->expires_at->toIso8601String(),
        ];
    }
}
