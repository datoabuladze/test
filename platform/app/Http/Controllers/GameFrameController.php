<?php

namespace App\Http\Controllers;

use App\Enums\GameEngine;
use App\Models\Game;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the document loaded inside the sandboxed player iframe.
 *
 * - original/html5/phaser: redirect to the static entry file on the games origin.
 * - ruffle: a wrapper page that runs the SWF in self-hosted Ruffle.
 * - unity: a wrapper page that boots a Unity WebGL build.
 * - iframe: never served here (the player embeds the authorized URL directly).
 */
class GameFrameController extends Controller
{
    public function show(Request $request, Game $game): Response
    {
        $canPreview = $request->user()?->hasPermission('games.manage') || $request->hasValidSignature(false);
        abort_unless($game->isPublic() || $canPreview, 404);

        $base = $this->assetOrigin();

        return match ($game->engine) {
            GameEngine::Original, GameEngine::Html5, GameEngine::Phaser => $this->frameHeaders(
                redirect()->away($base.'/'.ltrim((string) $game->entry_path, '/')
                    .($game->engine === GameEngine::Original ? '?lang='.\App\Http\Middleware\SetLocale::preferred($request) : ''))
            ),
            GameEngine::Ruffle => $this->frameHeaders(response()->view('frames.ruffle', [
                'game' => $game,
                'swfUrl' => $base.'/'.ltrim((string) $game->entry_path, '/'),
                'ruffleUrl' => $base.'/vendor/ruffle/ruffle.js',
            ])),
            GameEngine::Unity => $this->frameHeaders(response()->view('frames.unity', [
                'game' => $game,
                'buildUrl' => $base.'/'.trim(dirname((string) $game->entry_path), '/'),
                'config' => $game->engine_config ?? [],
            ])),
            GameEngine::Iframe => abort(404),
        };
    }

    private function assetOrigin(): string
    {
        return config('platform.games_origin') ?: rtrim(url('/'), '/');
    }

    private function frameHeaders(Response $response): Response
    {
        $app = rtrim(config('app.url'), '/');
        $assets = $this->assetOrigin();
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self' $assets data: blob:",
            "script-src 'self' $assets 'unsafe-inline' 'wasm-unsafe-eval' blob:",
            "style-src 'self' $assets 'unsafe-inline'",
            "img-src 'self' $assets data: blob:",
            "media-src 'self' $assets data: blob:",
            "connect-src 'self' $assets data: blob:",
            "worker-src 'self' $assets blob:",
            "frame-ancestors 'self' $app",
            "object-src 'none'",
            "base-uri 'none'",
        ]));
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $response->headers->set('Cache-Control', 'public, max-age=300');

        return $response;
    }
}
