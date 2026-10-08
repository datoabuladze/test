<?php

namespace App\Services;

use RuntimeException;

class PublishException extends RuntimeException
{
    /** @param list<string> $blockers */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('Game cannot be published: '.implode(' ', $blockers));
    }
}
