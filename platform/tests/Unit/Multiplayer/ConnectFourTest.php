<?php

namespace Tests\Unit\Multiplayer;

use App\Services\Multiplayer\ConnectFour;
use App\Services\Multiplayer\InvalidMove;
use PHPUnit\Framework\TestCase;

class ConnectFourTest extends TestCase
{
    private ConnectFour $rules;

    protected function setUp(): void
    {
        $this->rules = new ConnectFour;
    }

    /** @param list<int> $cols alternating players starting with player 0 */
    private function drop(array $cols, ?array $state = null): array
    {
        $state ??= $this->rules->initialState(0);
        foreach ($cols as $col) {
            $state = $this->rules->apply($state, $state['turn'], ['col' => $col]);
        }

        return $state;
    }

    public function test_piece_falls_to_the_bottom_row(): void
    {
        $s = $this->drop([3]);
        $this->assertSame(0, $s['board'][ConnectFour::ROWS - 1][3]);
        $this->assertSame([ConnectFour::ROWS - 1, 3], $s['last']);
        $this->assertSame(1, $s['turn']);
    }

    public function test_pieces_stack(): void
    {
        $s = $this->drop([3, 3]);
        $this->assertSame(0, $s['board'][5][3]);
        $this->assertSame(1, $s['board'][4][3]);
    }

    public function test_out_of_turn_move_is_rejected(): void
    {
        $this->expectException(InvalidMove::class);
        $this->expectExceptionMessage('Not your turn.');
        $this->rules->apply($this->rules->initialState(0), 1, ['col' => 0]);
    }

    public function test_illegal_columns_are_rejected(): void
    {
        foreach ([-1, 7, null, '2'] as $col) {
            try {
                $this->rules->apply($this->rules->initialState(0), 0, ['col' => $col]);
                $this->fail('Column '.var_export($col, true).' should be rejected');
            } catch (InvalidMove $e) {
                $this->assertSame('Invalid column.', $e->getMessage());
            }
        }
    }

    public function test_full_column_is_rejected(): void
    {
        $s = $this->drop([0, 0, 0, 0, 0, 0]);
        $this->expectException(InvalidMove::class);
        $this->expectExceptionMessage('Column is full.');
        $this->rules->apply($s, $s['turn'], ['col' => 0]);
    }

    public function test_horizontal_win(): void
    {
        $s = $this->drop([0, 0, 1, 1, 2, 2, 3]);
        $this->assertSame(0, $s['winner']);
        $this->assertCount(4, $s['line']);
    }

    public function test_vertical_win(): void
    {
        $s = $this->drop([0, 1, 0, 1, 0, 1, 0]);
        $this->assertSame(0, $s['winner']);
    }

    public function test_diagonal_win(): void
    {
        // Player 0 builds a rising diagonal from (5,0) to (2,3).
        $s = $this->drop([0, 1, 1, 2, 2, 3, 2, 3, 3, 6, 3]);
        $this->assertSame(0, $s['winner']);
        $this->assertCount(4, $s['line']);
    }

    public function test_no_moves_after_win(): void
    {
        $s = $this->drop([0, 1, 0, 1, 0, 1, 0]);
        $this->expectException(InvalidMove::class);
        $this->rules->apply($s, 1, ['col' => 5]);
    }

    public function test_three_in_a_row_is_not_a_win(): void
    {
        $s = $this->drop([0, 0, 1, 1, 2, 2]);
        $this->assertNull($s['winner']);
        $this->assertFalse($s['draw']);
    }
}
