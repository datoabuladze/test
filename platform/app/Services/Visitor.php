<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Privacy-preserving visitor attributes. No raw IP or fingerprint is stored:
 * the visitor hash is salted with a secret plus the current date, so it cannot
 * be linked across days or reversed.
 */
class Visitor
{
    public static function hash(Request $request): string
    {
        $salt = (string) config('platform.privacy.visitor_hash_salt');

        return hash('sha256', $salt.'|'.now()->toDateString().'|'.$request->ip().'|'.$request->userAgent());
    }

    public static function device(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /** Country only from a trusted CDN header (e.g. Cloudflare), never from IP lookup. */
    public static function country(Request $request): ?string
    {
        $c = strtoupper((string) $request->header('CF-IPCountry', ''));

        return preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' ? $c : null;
    }

    public static function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return $host && $host !== $request->getHost() ? substr($host, 0, 255) : null;
    }
}
