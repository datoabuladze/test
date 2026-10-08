<?php

namespace App\Enums;

enum FlashCompatibility: string
{
    case Untested = 'untested';
    case Compatible = 'compatible';
    case Partial = 'partial';
    case Unsupported = 'unsupported';
    case Broken = 'broken';

    public function isPlayable(): bool
    {
        return in_array($this, [self::Compatible, self::Partial], true);
    }
}
