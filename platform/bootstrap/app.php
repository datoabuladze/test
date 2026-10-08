<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetLocaleFromSession;
use App\Http\Middleware\TouchLastActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
        $middleware->web(append: [
            SecurityHeaders::class,
            TouchLastActive::class,
        ]);
        // Global, so it also sees 404s for paths that match no route at all.
        $middleware->append(HandleRedirects::class);
        $middleware->alias([
            'locale' => SetLocale::class,
            'locale.session' => SetLocaleFromSession::class,
            'permission' => EnsurePermission::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login', ['locale' => SetLocale::preferred($request)]));
        $middleware->redirectUsersTo(fn (Request $request) => route('home', ['locale' => SetLocale::preferred($request)]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
