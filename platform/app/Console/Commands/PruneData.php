<?php

namespace App\Console\Commands;

use App\Models\GameRoom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Expires multiplayer rooms and deletes data past its retention period (see the privacy policy). */
class PruneData extends Command
{
    protected $signature = 'platform:prune';

    protected $description = 'Expire rooms and delete stale score sessions, old raw plays and search logs';

    public function handle(): int
    {
        $expired = GameRoom::query()->where('expires_at', '<', now())->whereIn('status', ['waiting', 'playing'])->update(['status' => 'expired']);
        $rooms = GameRoom::query()->where('expires_at', '<', now()->subDay())->delete();
        $sessions = DB::table('score_sessions')->where('started_at', '<', now()->subDay())->delete();
        // Raw plays are kept 25 months and searches 12 months, as stated in the privacy policy; daily aggregates are kept.
        $plays = DB::table('game_plays')->where('created_at', '<', now()->subMonths(25))->delete();
        $searches = DB::table('search_queries')->where('created_at', '<', now()->subMonths(12))->delete();

        $this->info("Rooms expired: $expired, deleted: $rooms. Score sessions: $sessions. Plays: $plays. Searches: $searches.");

        return self::SUCCESS;
    }
}
