<?php

namespace App\Services;

use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Game;
use Illuminate\Support\Carbon;

/**
 * The only path to making a game public. Enforces the rights and technical
 * gates so that unverified, unlicensed or broken games are never shown.
 */
class GamePublisher
{
    /** @return list<string> reasons the game cannot be published (empty = OK) */
    public function blockers(Game $game): array
    {
        $errors = [];
        if ($game->rights_status !== RightsStatus::Verified) {
            $errors[] = 'Publication rights have not been verified.';
        }
        if (! $game->license_type) {
            $errors[] = 'License type is missing.';
        }
        if (! $game->is_original && ! $game->source_url) {
            $errors[] = 'Source URL is missing.';
        }
        if (! $game->hosting_method) {
            $errors[] = 'Permitted hosting method is missing.';
        }
        if ($game->engine === GameEngine::Iframe) {
            if (! $game->embed_url) {
                $errors[] = 'Embed URL is missing.';
            }
            if (! $game->embed_authorized) {
                $errors[] = 'Embedding is not marked as authorized.';
            }
            if ($game->embed_url && ! app(EmbedValidator::class)->isAllowed($game)) {
                $errors[] = 'Embed URL host is not on the provider allow-list or is not HTTPS.';
            }
        } elseif (! $game->entry_path) {
            $errors[] = 'Game files (entry path) are missing.';
        }
        if ($game->engine === GameEngine::Ruffle && ! $game->flash_compatibility?->isPlayable()) {
            $errors[] = 'Flash compatibility must be Compatible or Partially compatible.';
        }
        if ($game->launch_status === 'failed') {
            $errors[] = 'The last launch check failed.';
        }
        if ($game->thumbnail_path && ! $game->thumbnail_rights && ! $game->is_original) {
            $errors[] = 'Thumbnail usage rights are not confirmed.';
        }
        if (! $game->hasTranslation('title', 'en')) {
            $errors[] = 'English title is missing.';
        }

        return $errors;
    }

    /** @throws PublishException */
    public function publish(Game $game, ?Carbon $at = null): void
    {
        $blockers = $this->blockers($game);
        if ($blockers) {
            throw new PublishException($blockers);
        }
        $game->status = GameStatus::Published;
        $game->published_at = $at ?? ($game->published_at && $game->published_at->isFuture() ? $game->published_at : now());
        $game->save();
        GameCatalog::flush();
        Audit::log('game.publish', $game, ['at' => $game->published_at->toIso8601String()]);
    }

    public function unpublish(Game $game): void
    {
        $game->status = GameStatus::Unpublished;
        $game->save();
        GameCatalog::flush();
        Audit::log('game.unpublish', $game);
    }
}
