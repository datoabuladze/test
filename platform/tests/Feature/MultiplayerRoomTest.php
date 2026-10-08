<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Services\Multiplayer\InvalidMove;
use App\Services\Multiplayer\RoomManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MultiplayerRoomTest extends TestCase
{
    use RefreshDatabase;

    /** Seat token cookies per simulated browser. */
    private array $seats = [];

    /** Sends a JSON request as one of the simulated browsers ('host', 'guest', 'stranger'). */
    private function as(string $client, string $method, string $uri, array $data = []): TestResponse
    {
        $this->defaultCookies = [];
        $this->withCredentials();
        foreach ($this->seats[$client] ?? [] as $name => $value) {
            $this->withCookie($name, $value);
        }
        $response = $this->json($method, $uri, $data);
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'room_')) {
                $this->seats[$client][$cookie->getName()] = $response->getCookie($cookie->getName())->getValue();
            }
        }

        return $response;
    }

    private function createRoom(string $game): string
    {
        $res = $this->as('host', 'POST', '/api/rooms', ['game' => $game])->assertCreated();
        $code = $res->json('code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code);
        $this->assertArrayHasKey('room_'.$code, $this->seats['host']);

        return $code;
    }

    private function startedRoom(string $game): string
    {
        $code = $this->createRoom($game);
        $this->as('guest', 'POST', "/api/rooms/$code/join")->assertOk()->assertJson(['you' => 1]);
        $this->as('host', 'POST', "/api/rooms/$code/ready")->assertOk()->assertJson(['status' => 'waiting']);
        $this->as('guest', 'POST', "/api/rooms/$code/ready")->assertOk()->assertJson(['status' => 'playing']);

        return $code;
    }

    public function test_create_room_validates_game(): void
    {
        $this->postJson('/api/rooms', ['game' => 'chess'])->assertUnprocessable()->assertJsonValidationErrors('game');
        $this->assertSame(0, GameRoom::query()->count());
    }

    public function test_create_and_join_tictactoe(): void
    {
        $code = $this->createRoom('tictactoe');

        $this->as('host', 'GET', "/api/rooms/$code")->assertOk()
            ->assertJson(['code' => $code, 'game' => 'tictactoe', 'status' => 'waiting', 'you' => 0, 'guest' => ['joined' => false]]);

        $this->as('guest', 'POST', "/api/rooms/$code/join")->assertOk()->assertJson(['you' => 1, 'guest' => ['joined' => true]]);
        // Joining again with the same seat cookie just returns state (reconnect).
        $this->as('guest', 'POST', "/api/rooms/$code/join")->assertOk()->assertJson(['you' => 1]);
        // A third browser cannot take a seat.
        $this->as('stranger', 'POST', "/api/rooms/$code/join")->assertStatus(409);
        $this->as('stranger', 'GET', "/api/rooms/$code")->assertOk()->assertJson(['you' => null]);
    }

    public function test_tictactoe_moves_turns_and_win(): void
    {
        $code = $this->startedRoom('tictactoe');

        // Round 1: host (seat 0) moves first.
        $this->as('guest', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertUnprocessable()->assertJson(['message' => 'Not your turn.']);
        $this->as('host', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertOk()->assertJsonPath('state.game.board.0', 0);
        $this->as('guest', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertUnprocessable()->assertJson(['message' => 'Cell already taken.']);
        $this->as('guest', 'POST', "/api/rooms/$code/move", ['cell' => 3])->assertOk();
        $this->as('host', 'POST', "/api/rooms/$code/move", ['cell' => 1])->assertOk();
        $this->as('guest', 'POST', "/api/rooms/$code/move", ['cell' => 4])->assertOk();
        $this->as('host', 'POST', "/api/rooms/$code/move", ['cell' => 2])->assertOk()
            ->assertJson(['status' => 'finished'])
            ->assertJsonPath('state.game.winner', 0)
            ->assertJsonPath('state.game.line', [0, 1, 2])
            ->assertJsonPath('state.score', [1, 0]);

        $this->as('guest', 'POST', "/api/rooms/$code/move", ['cell' => 8])->assertUnprocessable();

        // Rematch alternates the first player.
        $this->as('guest', 'POST', "/api/rooms/$code/rematch")->assertOk()
            ->assertJson(['status' => 'playing'])->assertJsonPath('state.game.turn', 1)->assertJsonPath('state.round', 2);
    }

    public function test_moves_before_start_and_by_spectators_are_rejected(): void
    {
        $code = $this->createRoom('tictactoe');
        $this->as('host', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertUnprocessable()->assertJson(['message' => 'The game has not started.']);

        $this->as('guest', 'POST', "/api/rooms/$code/join");
        $this->as('host', 'POST', "/api/rooms/$code/ready");
        $this->as('guest', 'POST', "/api/rooms/$code/ready");
        $this->as('stranger', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertForbidden();
    }

    public function test_forged_seat_cookie_is_not_a_player(): void
    {
        $code = $this->startedRoom('tictactoe');
        $this->seats['stranger'] = ['room_'.$code => str_repeat('a', 48)];
        $this->as('stranger', 'POST', "/api/rooms/$code/move", ['cell' => 0])->assertForbidden();
    }

    public function test_connect4_moves_illegal_column_and_win(): void
    {
        $code = $this->startedRoom('connect4');

        $this->as('host', 'POST', "/api/rooms/$code/move", ['col' => 7])->assertUnprocessable()->assertJson(['message' => 'Invalid column.']);
        $this->as('host', 'POST', "/api/rooms/$code/move", ['col' => -1])->assertUnprocessable();
        $this->as('host', 'POST', "/api/rooms/$code/move", ['cell' => 3])->assertUnprocessable()->assertJson(['message' => 'Invalid column.']);
        $this->as('guest', 'POST', "/api/rooms/$code/move", ['col' => 0])->assertUnprocessable()->assertJson(['message' => 'Not your turn.']);

        foreach ([[0, 'host'], [1, 'guest'], [0, 'host'], [1, 'guest'], [0, 'host'], [1, 'guest']] as [$col, $who]) {
            $this->as($who, 'POST', "/api/rooms/$code/move", ['col' => $col])->assertOk()->assertJson(['status' => 'playing']);
        }
        $this->as('host', 'POST', "/api/rooms/$code/move", ['col' => 0])->assertOk()
            ->assertJson(['status' => 'finished'])->assertJsonPath('state.game.winner', 0);

        $room = GameRoom::query()->where('code', $code)->sole();
        $this->assertSame('finished', $room->status);
        $this->assertSame([1, 0], $room->state['score']);
    }

    public function test_expired_room_is_gone(): void
    {
        $code = $this->createRoom('connect4');
        $this->travel(RoomManager::TTL_MINUTES + 1)->minutes();

        $this->as('host', 'GET', "/api/rooms/$code")->assertStatus(410);
        $this->assertSame('expired', GameRoom::query()->where('code', $code)->value('status'));
    }

    public function test_room_page_renders(): void
    {
        $code = $this->createRoom('tictactoe');
        $this->get("/en/play-together/$code")->assertOk();
        $this->get('/en/play-together')->assertOk();
    }

    public function test_room_manager_service_flow(): void
    {
        $rooms = app(RoomManager::class);
        $room = $rooms->create('tictactoe', null);
        $guestToken = $rooms->join($room, null);

        $this->assertSame(0, $rooms->seat($room, $room->host_token));
        $this->assertSame(1, $rooms->seat($room, $guestToken));
        $this->assertNull($rooms->seat($room, 'nope'));
        $this->assertNull($rooms->seat($room, null));

        try {
            $rooms->join($room, null);
            $this->fail('Room should be full');
        } catch (InvalidMove $e) {
            $this->assertSame('This room is full.', $e->getMessage());
        }

        $rooms->ready($room, 0);
        $rooms->ready($room, 1);
        $this->assertSame('playing', $room->status);

        $this->expectException(InvalidMove::class);
        $rooms->rules('chess');
    }
}
