<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Admin-managed redirects, applied only when the app would otherwise 404. */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404 && $request->isMethod('GET')) {
            $path = '/'.ltrim($request->path(), '/');
            $map = Cache::rememberForever('redirects.map', fn () => Redirect::query()->pluck('id', 'from_path')->all());
            if (isset($map[$path]) && ($redirect = Redirect::find($map[$path]))) {
                $redirect->increment('hits');

                return redirect($redirect->to_url, in_array($redirect->status_code, [301, 302, 307, 308]) ? $redirect->status_code : 301);
            }
        }

        return $response;
    }
}
