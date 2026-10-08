<?php

/*
| Development router for `php -S 127.0.0.1:8000 -t public scripts/dev-router.php`.
| Mirrors the production web-server rules that matter for games:
| - game files, uploaded packages and Ruffle get `Access-Control-Allow-Origin: *`,
|   because sandboxed game frames have an opaque ("null") origin;
| - nothing under game-files/ or games/ is ever executed as PHP.
*/
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$public = realpath(__DIR__.'/../public');
$file = realpath($public.$path);

if ($file && str_starts_with($file, $public) && is_file($file)) {
    if (preg_match('#^/(games|game-files|vendor/ruffle)/#', $path)) {
        if (str_ends_with($file, '.php')) {
            http_response_code(404);
            exit;
        }
        $types = ['html' => 'text/html; charset=utf-8', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'css' => 'text/css',
            'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp',
            'wasm' => 'application/wasm', 'swf' => 'application/x-shockwave-flash', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
            'data' => 'application/octet-stream', 'woff2' => 'font/woff2'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
        header('Access-Control-Allow-Origin: *');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: cross-origin');
        header('Content-Length: '.filesize($file));
        readfile($file);
        exit;
    }

    return false; // let the built-in server handle other static files
}

require $public.'/index.php';
