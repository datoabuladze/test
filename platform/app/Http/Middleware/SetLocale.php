<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the {locale} URL prefix to the app and URL generator, then removes the
 * parameter so controllers receive only their own route parameters.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $supported = array_keys(config('platform.locales'));

        if (! in_array($locale, $supported, true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()->forgetParameter('locale');

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    public static function preferred(Request $request): string
    {
        $supported = array_keys(config('platform.locales'));
        $fromSession = $request->hasSession() ? $request->session()->get('locale') : null;
        if (in_array($fromSession, $supported, true)) {
            return $fromSession;
        }
        if ($request->user() && in_array($request->user()->locale, $supported, true)) {
            return $request->user()->locale;
        }

        return $request->getPreferredLanguage($supported) ?: config('platform.default_locale');
    }
}
