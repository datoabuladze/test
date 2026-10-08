<?php

namespace App\Enums;

enum FlashCompatibility: string
{
    case Untested = 'untested';
    case Compatible = 'compatible';
    case Partial = 'partial';
    case Unsupported = 'unsupported';
    case Broken = 'broken';

    public function label(): string
    {
        return match ($this) {
            self::Untested => 'Untested',
            self::Compatible => 'Compatible',
            self::Partial => 'Partially compatible',
            self::Unsupported => 'Unsupported',
            self::Broken => 'Broken',
        };
    }

    public function isPlayable(): bool
    {
        return in_array($this, [self::Compatible, self::Partial], true);
    }
}
