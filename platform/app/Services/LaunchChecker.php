<?php

namespace App\Services;

use App\Enums\GameEngine;
use App\Models\Game;
use Illuminate\Support\Facades\Http;

/**
 * Technical launch check. Confirms the game's files or embed URL are reachable and
 * well-formed. This does NOT prove a game is playable: interactive verification is
 * done by the browser smoke test (php artisan games:smoke), which records results too.
 */
class LaunchChecker
{
    /** @return array{ok: bool, message: string} */
    public function check(Game $game): array
    {
        $result = match ($game->engine) {
            GameEngine::Iframe => $this->checkEmbed($game),
            default => $this->checkLocal($game),
        };
        $game->forceFill([
            'launch_status' => $result['ok'] ? ($game->launch_status === 'ok' ? 'ok' : 'untested') : 'failed',
            'last_checked_at' => now(),
            'last_check_message' => $result['message'],
        ])->saveQuietly();
        GameCatalog::flush();

        return $result;
    }

    private function checkLocal(Game $game): array
    {
        $path = public_path(ltrim((string) $game->entry_path, '/'));
        if (! $game->entry_path || ! is_file($path) || ! str_starts_with(realpath($path) ?: '', public_path())) {
            return ['ok' => false, 'message' => 'Entry file not found: '.$game->entry_path];
        }
        if ($game->engine === GameEngine::Ruffle) {
            $sig = (string) file_get_contents($path, false, null, 0, 3);

            return in_array($sig, ['FWS', 'CWS', 'ZWS'], true)
                ? ['ok' => true, 'message' => 'SWF file present ('.$sig.'). Compatibility must be confirmed by playing it.']
                : ['ok' => false, 'message' => 'SWF signature invalid.'];
        }
        if ($game->engine === GameEngine::Unity) {
            $dir = dirname($path);
            foreach (['loader', 'data', 'framework', 'code'] as $k) {
                $f = $game->engine_config[$k] ?? null;
                if (! $f || ! is_file($dir.'/'.$f)) {
                    return ['ok' => false, 'message' => "Unity build file missing: $k"];
                }
            }
        }
        $html = (string) file_get_contents($path, false, null, 0, 100000);
        if (! preg_match('/<(html|body|canvas|script)/i', $html)) {
            return ['ok' => false, 'message' => 'Entry file does not look like an HTML document.'];
        }

        return ['ok' => true, 'message' => 'Entry file present and well-formed.'];
    }

    private function checkEmbed(Game $game): array
    {
        if (! app(EmbedValidator::class)->isAllowed($game)) {
            return ['ok' => false, 'message' => 'Embed URL is not HTTPS or its host is not allow-listed for the provider.'];
        }
        try {
            $res = Http::timeout(10)->withHeaders(['User-Agent' => config('platform.brand').'-LinkCheck/1.0'])->get($game->embed_url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Embed URL unreachable: '.class_basename($e)];
        }
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Embed URL returned HTTP '.$res->status()];
        }
        $xfo = strtolower((string) $res->header('X-Frame-Options'));
        $csp = strtolower((string) $res->header('Content-Security-Policy'));
        if (in_array($xfo, ['deny', 'sameorigin'], true) || preg_match("/frame-ancestors\s+'none'/", $csp)) {
            return ['ok' => false, 'message' => 'The provider forbids embedding this URL (X-Frame-Options/CSP).'];
        }

        return ['ok' => true, 'message' => 'Embed URL reachable (HTTP '.$res->status().').'];
    }
}
