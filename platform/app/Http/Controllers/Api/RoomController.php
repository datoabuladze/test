<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRoom;
use App\Services\Multiplayer\InvalidMove;
use App\Services\Multiplayer\RoomManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Private two-player rooms. The server owns the game state and validates every move;
 * clients only send intents. Seats are bound to an httpOnly cookie per room, which
 * also makes reconnection after a reload or network drop automatic.
 */
class RoomController extends Controller
{
    public function __construct(private RoomManager $rooms) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['game' => ['required', 'in:'.implode(',', array_keys(RoomManager::GAMES))]]);
        $room = $this->rooms->create($data['game'], $request->user()?->id);

        return response()->json([
            'code' => $room->code,
            'url' => route('rooms.show', ['locale' => app()->getLocale(), 'room' => $room->code]),
        ], 201)->withCookie($this->seatCookie($room, $room->host_token));
    }

    public function join(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureAlive($room);
        if ($this->rooms->seat($room, $this->token($request, $room)) !== null) {
            return $this->state($request, $room);
        }
        try {
            $token = DB::transaction(fn () => $this->rooms->join(GameRoom::query()->lockForUpdate()->findOrFail($room->id), $request->user()?->id));
        } catch (InvalidMove $e) {
            return response()->json(['message' => __($e->getMessage())], 409);
        }
        $room->refresh();

        return response()->json($this->rooms->payload($room, 1))->withCookie($this->seatCookie($room, $token));
    }

    public function state(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureAlive($room);
        $seat = $this->rooms->seat($room, $this->token($request, $room));
        $this->rooms->touch($room, $seat);

        return response()->json($this->rooms->payload($room, $seat));
    }

    public function ready(Request $request, GameRoom $room): JsonResponse
    {
        return $this->act($request, $room, fn (GameRoom $r, int $seat) => $this->rooms->ready($r, $seat));
    }

    public function move(Request $request, GameRoom $room): JsonResponse
    {
        $move = $request->validate(['cell' => ['nullable', 'integer'], 'col' => ['nullable', 'integer']]);
        $move = array_map('intval', array_filter($move, fn ($v) => $v !== null));

        return $this->act($request, $room, fn (GameRoom $r, int $seat) => $this->rooms->move($r, $seat, $move));
    }

    public function rematch(Request $request, GameRoom $room): JsonResponse
    {
        return $this->act($request, $room, fn (GameRoom $r, int $seat) => $this->rooms->rematch($r, $seat));
    }

    private function act(Request $request, GameRoom $room, \Closure $action): JsonResponse
    {
        $this->ensureAlive($room);
        $seat = $this->rooms->seat($room, $this->token($request, $room));
        if ($seat === null) {
            return response()->json(['message' => __('You are not a player in this room.')], 403);
        }
        try {
            $fresh = DB::transaction(function () use ($room, $seat, $action) {
                $locked = GameRoom::query()->lockForUpdate()->findOrFail($room->id);
                $action($locked, $seat);

                return $locked;
            });
        } catch (InvalidMove $e) {
            return response()->json(['message' => __($e->getMessage())], 422);
        }
        $this->rooms->touch($fresh, $seat);

        return response()->json($this->rooms->payload($fresh, $seat));
    }

    private function ensureAlive(GameRoom $room): void
    {
        if ($room->expires_at->isPast() || $room->status === 'expired') {
            if ($room->status !== 'expired') {
                $room->forceFill(['status' => 'expired'])->saveQuietly();
            }
            abort(410, __('This room has expired.'));
        }
    }

    private function token(Request $request, GameRoom $room): ?string
    {
        return $request->cookie('room_'.$room->code);
    }

    private function seatCookie(GameRoom $room, string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make('room_'.$room->code, $token, RoomManager::TTL_MINUTES * 4, '/', null, null, true, false, 'lax');
    }
}
