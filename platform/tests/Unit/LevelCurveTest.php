<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class LevelCurveTest extends TestCase
{
    public function test_xp_for_level_follows_the_curve(): void
    {
        $this->assertSame(0, User::xpForLevel(1));
        $this->assertSame(0, User::xpForLevel(0));
        $this->assertSame(100, User::xpForLevel(2));
        $this->assertSame(283, User::xpForLevel(3)); // 100 * 2^1.5
        $this->assertSame(2700, User::xpForLevel(10)); // 100 * 9^1.5
    }

    public function test_level_for_xp_uses_thresholds(): void
    {
        $this->assertSame(1, User::levelForXp(0));
        $this->assertSame(1, User::levelForXp(99));
        $this->assertSame(2, User::levelForXp(100));
        $this->assertSame(2, User::levelForXp(282));
        $this->assertSame(3, User::levelForXp(283));
        $this->assertSame(10, User::levelForXp(2700));
    }

    public function test_level_and_threshold_are_consistent(): void
    {
        for ($level = 1; $level <= 50; $level++) {
            $this->assertSame($level, User::levelForXp(User::xpForLevel($level)));
            if ($level > 1) {
                $this->assertSame($level - 1, User::levelForXp(User::xpForLevel($level) - 1));
            }
        }
    }
}
