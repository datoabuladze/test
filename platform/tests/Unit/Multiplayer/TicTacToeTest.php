<?php

namespace Tests\Unit\Multiplayer;

use App\Services\Multiplayer\InvalidMove;
use App\Services\Multiplayer\TicTacToe;
use PHPUnit\Framework\TestCase;

class TicTacToeTest extends TestCase
{
    private TicTacToe $rules;

    protected function setUp(): void
    {
        $this->rules = new TicTacToe;
    }

    private function play(array $state, array $moves): array
    {
        foreach ($moves as [$player, $cell]) {
            $state = $this->rules->apply($state, $player, ['cell' => $cell]);
        }

        return $state;
    }

    public function test_initial_state(): void
    {
        $s = $this->rules->initialState(1);
        $this->assertSame(array_fill(0, 9, null), $s['board']);
        $this->assertSame(1, $s['turn']);
        $this->assertNull($s['winner']);
        $this->assertFalse($s['draw']);
    }

    public function test_legal_move_places_mark_and_passes_turn(): void
    {
        $s = $this->rules->apply($this->rules->initialState(0), 0, ['cell' => 4]);
        $this->assertSame(0, $s['board'][4]);
        $this->assertSame(1, $s['turn']);
        $this->assertSame(1, $s['moves']);
    }

    public function test_out_of_turn_move_is_rejected(): void
    {
        $this->expectException(InvalidMove::class);
        $this->expectExceptionMessage('Not your turn.');
        $this->rules->apply($this->rules->initialState(0), 1, ['cell' => 0]);
    }

    public function test_taken_cell_is_rejected(): void
    {
        $s = $this->rules->apply($this->rules->initialState(0), 0, ['cell' => 0]);
        $this->expectException(InvalidMove::class);
        $this->rules->apply($s, 1, ['cell' => 0]);
    }

    public function test_out_of_range_and_non_integer_cells_are_rejected(): void
    {
        foreach ([-1, 9, '3', null] as $cell) {
            try {
                $this->rules->apply($this->rules->initialState(0), 0, ['cell' => $cell]);
                $this->fail('Cell '.var_export($cell, true).' should be rejected');
            } catch (InvalidMove $e) {
                $this->assertSame('Invalid cell.', $e->getMessage());
            }
        }
    }

    public function test_row_win_is_detected(): void
    {
        $s = $this->play($this->rules->initialState(0), [[0, 0], [1, 3], [0, 1], [1, 4], [0, 2]]);
        $this->assertSame(0, $s['winner']);
        $this->assertSame([0, 1, 2], $s['line']);
    }

    public function test_diagonal_win_for_second_player(): void
    {
        $s = $this->play($this->rules->initialState(0), [[0, 1], [1, 0], [0, 2], [1, 4], [0, 3], [1, 8]]);
        $this->assertSame(1, $s['winner']);
        $this->assertSame([0, 4, 8], $s['line']);
    }

    public function test_moves_after_game_over_are_rejected(): void
    {
        $s = $this->play($this->rules->initialState(0), [[0, 0], [1, 3], [0, 1], [1, 4], [0, 2]]);
        $this->expectException(InvalidMove::class);
        $this->expectExceptionMessage('The game is over.');
        $this->rules->apply($s, 1, ['cell' => 8]);
    }

    public function test_full_board_without_line_is_a_draw(): void
    {
        // X O X / X O O / O X X
        $s = $this->play($this->rules->initialState(0), [
            [0, 0], [1, 1], [0, 2], [1, 4], [0, 3], [1, 5], [0, 7], [1, 6], [0, 8],
        ]);
        $this->assertTrue($s['draw']);
        $this->assertNull($s['winner']);
    }
}
