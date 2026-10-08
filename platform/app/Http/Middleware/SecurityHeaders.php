<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers and a nonce-based Content Security Policy for app pages.
 * Game frame documents get their own policy in GameFrameController.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        // Game frame documents set their own frame-ancestors policy.
        if (! $request->routeIs('games.frame') && ! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $headers->has('Content-Security-Policy') && str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $games = config('platform.games_origin');
        $frameSrc = array_filter(["'self'", $games ?: null, 'https:']);
        $dev = app()->environment('local') ? ' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173' : '';
        $adsense = config('platform.ads.adsense_client') ? ' https://pagead2.googlesyndication.com https://*.googlesyndication.com https://*.doubleclick.net' : '';
        $ga = config('platform.analytics.ga4_measurement_id') ? ' https://www.googletagmanager.com https://*.google-analytics.com' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$dev}{$adsense}{$ga}",
            "style-src 'self' 'unsafe-inline'{$dev}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'{$dev}{$ga}{$adsense}",
            'frame-src '.implode(' ', $frameSrc),
            "media-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
