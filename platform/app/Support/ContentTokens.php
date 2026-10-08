<?php

namespace App\Support;

/** Replaces operator tokens in CMS content (legal pages are written once, filled per deployment). */
class ContentTokens
{
    public static function apply(string $text): string
    {
        return strtr($text, [
            ':brand' => config('platform.brand'),
            ':operator' => config('platform.legal.operator'),
            ':contact_email' => config('platform.legal.contact_email'),
            ':jurisdiction' => config('platform.legal.jurisdiction'),
            ':updated' => now()->toDateString(),
        ]);
    }
}
