<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** For non-prefixed routes (account actions, verification links): use the visitor's last locale. */
class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = SetLocale::preferred($request);
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
