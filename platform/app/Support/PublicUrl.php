<?php

namespace App\Support;

/**
 * Guards server-side fetches of staff-supplied URLs (provider feeds, import thumbnails)
 * against requests to internal services: the URL must be HTTPS and every address its
 * host resolves to must be public. Redirect targets are checked with the same rule.
 *
 * Residual risk: DNS can change between this check and the request (rebinding); the
 * fetches are limited to staff with import permissions, which keeps that exposure small.
 */
class PublicUrl
{
    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        return $ips !== [] && collect($ips)->every(fn (string $ip) => self::isPublicIp($ip));
    }

    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Guzzle `allow_redirects` options that re-check every redirect target. */
    public static function redirectOptions(int $max = 2): array
    {
        return ['max' => $max, 'protocols' => ['https'], 'on_redirect' => function ($request, $response, $uri) {
            if (! self::allowed((string) $uri)) {
                throw new \RuntimeException('Redirect to a non-public address refused.');
            }
        }];
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
