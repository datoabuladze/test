<?php

namespace App\Console\Commands;

use App\Enums\GameEngine;
use App\Http\Controllers\GameController;
use App\Models\Game;
use App\Services\GameCatalog;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Real-browser launch test. Records launch_status ok/failed per game. A game that
 * fails stops being public (Game::public() excludes failed launches) until fixed.
 * Requires Node, Playwright and Chromium, and the app reachable at APP_URL.
 */
class SmokeTestGames extends Command
{
    protected $signature = 'games:smoke
        {--game=* : Slugs to test (default: all games with files or an embed URL)}
        {--engine= : Only this engine}
        {--untested : Only games never tested or not tested in 7 days}
        {--dry-run : Print results without saving}';

    protected $description = 'Load games in a headless browser inside the production sandbox and record launch status';

    public function handle(): int
    {
        $q = Game::query()->where(fn ($w) => $w->whereNotNull('entry_path')->orWhereNotNull('embed_url'));
        if ($slugs = $this->option('game')) {
            $q->whereIn('slug', $slugs);
        }
        if ($engine = $this->option('engine')) {
            $q->where('engine', $engine);
        }
        if ($this->option('untested')) {
            $q->where(fn ($w) => $w->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subDays(7))->orWhere('launch_status', 'untested'));
        }
        $games = $q->orderBy('id')->get();
        if ($games->isEmpty()) {
            $this->info('No games to test.');

            return self::SUCCESS;
        }

        // Frame URLs for non-public games need a signature, exactly like the admin preview.
        $payload = [
            'appUrl' => rtrim(config('app.url'), '/'),
            'sandbox' => config('platform.iframe_sandbox'),
            'allow' => config('platform.iframe_allow'),
            'games' => $games->map(fn (Game $g) => [
                'id' => $g->id,
                'engine' => $g->engine->value,
                'frame_url' => GameController::frameUrl($g, preview: ! $g->isPublic() && $g->engine !== GameEngine::Iframe),
            ])->values()->all(),
        ];
        $file = tempnam(sys_get_temp_dir(), 'smoke').'.json';
        file_put_contents($file, json_encode($payload));

        $this->info("Testing {$games->count()} games in Chromium…");
        $process = new Process(['node', base_path('tests/browser/catalog-smoke.mjs'), $file], base_path(), null, null, 60 + 30 * $games->count());
        $process->run();
        @unlink($file);

        $lines = array_filter(explode("\n", trim($process->getOutput())));
        $results = json_decode((string) end($lines), true);
        if (! is_array($results)) {
            $this->error('Browser test did not return results: '.trim($process->getErrorOutput() ?: $process->getOutput()));

            return self::FAILURE;
        }

        $byId = $games->keyBy('id');
        $failed = 0;
        $rows = [];
        foreach ($results as $r) {
            $game = $byId[$r['id']] ?? null;
            if (! $game) {
                continue;
            }
            $failed += $r['ok'] ? 0 : 1;
            $rows[] = [$game->slug, $game->engine->value, $r['ok'] ? 'ok' : 'FAILED', $r['ms'].' ms', mb_strimwidth($r['message'], 0, 90, '…')];
            if (! $this->option('dry-run')) {
                $game->forceFill([
                    'launch_status' => $r['ok'] ? 'ok' : 'failed',
                    'last_checked_at' => now(),
                    'last_check_message' => 'Browser test: '.$r['message'],
                ])->saveQuietly();
            }
        }
        GameCatalog::flush();
        $this->table(['Game', 'Engine', 'Result', 'Time', 'Message'], $rows);
        $this->line(($games->count() - $failed)." passed, $failed failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
