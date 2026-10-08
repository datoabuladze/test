<?php

namespace App\Services\Verifiers;

/** PHP port of the mulberry32 PRNG used by the original games' shared SDK (must match bit-for-bit). */
class Mulberry32
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    public function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $this->state;
        $t = $this->imul($t ^ ($t >> 15), $t | 1);
        $t ^= ($t + $this->imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;
        $t &= 0xFFFFFFFF;

        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
    }

    /** 32-bit integer multiply (JS Math.imul), result as unsigned 32-bit. */
    private function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $lo = (($a & 0xFFFF) * $b) & 0xFFFFFFFF;
        $hi = ((($a >> 16) & 0xFFFF) * $b) & 0xFFFF;

        return ($lo + ($hi << 16)) & 0xFFFFFFFF;
    }
}
