<?php

namespace App\Services\Verifiers;

interface ScoreVerifier
{
    /** Re-simulate a run deterministically; return the resulting score or null if evidence is invalid. */
    public function replay(int $seed, array $evidence): ?int;
}
