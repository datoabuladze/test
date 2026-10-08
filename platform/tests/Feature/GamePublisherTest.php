<?php

namespace Tests\Feature;

use App\Enums\FlashCompatibility;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Game;
use App\Models\Provider;
use App\Services\GamePublisher;
use App\Services\PublishException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePublisherTest extends TestCase
{
    use RefreshDatabase;

    private function blockers(Game $game): array
    {
        return app(GamePublisher::class)->blockers($game->fresh());
    }

    private function assertBlockedBy(Game $game, string $needle): void
    {
        $blockers = $this->blockers($game);
        $this->assertNotEmpty(
            array_filter($blockers, fn ($b) => str_contains($b, $needle)),
            "Expected a blocker containing \"$needle\", got: ".json_encode($blockers),
        );
    }

    public function test_ready_game_has_no_blockers(): void
    {
        $this->assertSame([], $this->blockers(Game::factory()->draft()->create()));
        $this->assertSame([], $this->blockers(Game::factory()->iframe()->draft()->create()));
        $this->assertSame([], $this->blockers(Game::factory()->original()->draft()->create()));
    }

    public function test_unverified_rights_block(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->unverified()->create(), 'rights have not been verified');
        $this->assertBlockedBy(Game::factory()->draft()->create(['rights_status' => RightsStatus::Rejected]), 'rights have not been verified');
    }

    public function test_missing_license_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['license_type' => null]), 'License type is missing');
    }

    public function test_missing_source_blocks_third_party_but_not_originals(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['source_url' => null]), 'Source URL is missing');
        $this->assertSame([], $this->blockers(Game::factory()->original()->draft()->create(['source_url' => null])));
    }

    public function test_missing_hosting_method_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['hosting_method' => null]), 'hosting method is missing');
    }

    public function test_self_hosted_game_without_files_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['entry_path' => null]), 'entry path');
    }

    public function test_iframe_embed_url_and_authorization_required(): void
    {
        $this->assertBlockedBy(Game::factory()->iframe()->draft()->create(['embed_url' => null]), 'Embed URL is missing');
        $this->assertBlockedBy(Game::factory()->iframe()->draft()->create(['embed_authorized' => false]), 'not marked as authorized');
    }

    public function test_iframe_host_must_be_on_provider_allow_list(): void
    {
        $this->assertBlockedBy(Game::factory()->iframe('https://evil.example.org/game')->draft()->create(), 'allow-list');
        // Wildcard entries match subdomains only.
        $this->assertSame([], $this->blockers(Game::factory()->iframe('https://a.cdn.example.net/g')->draft()->create()));
        $this->assertBlockedBy(Game::factory()->iframe('https://cdn.example.net.evil.com/g')->draft()->create(), 'allow-list');
    }

    public function test_iframe_embed_must_be_https(): void
    {
        $this->assertBlockedBy(Game::factory()->iframe('http://games.example.com/play/1')->draft()->create(), 'not HTTPS');
    }

    public function test_iframe_embed_with_credentials_or_without_provider_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->iframe('https://user:pw@games.example.com/x')->draft()->create(), 'allow-list');
        $this->assertBlockedBy(Game::factory()->iframe()->draft()->create(['provider_id' => null]), 'allow-list');
    }

    public function test_ruffle_requires_playable_compatibility(): void
    {
        foreach ([FlashCompatibility::Untested, FlashCompatibility::Unsupported, FlashCompatibility::Broken] as $c) {
            $this->assertBlockedBy(Game::factory()->ruffle($c)->draft()->create(), 'Flash compatibility');
        }
        $this->assertSame([], $this->blockers(Game::factory()->ruffle(FlashCompatibility::Partial)->draft()->create()));
    }

    public function test_failed_launch_check_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['launch_status' => 'failed']), 'launch check failed');
    }

    public function test_thumbnail_without_rights_blocks_third_party_games(): void
    {
        $this->assertBlockedBy(
            Game::factory()->draft()->create(['thumbnail_path' => 'thumbnails/x.webp', 'thumbnail_rights' => false]),
            'Thumbnail usage rights',
        );
        $this->assertSame([], $this->blockers(
            Game::factory()->original()->draft()->create(['thumbnail_path' => 'thumbnails/x.webp', 'thumbnail_rights' => false])
        ));
    }

    public function test_missing_english_title_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['title' => ['ka' => 'თამაში']]), 'English title is missing');
    }

    public function test_completely_missing_title_blocks(): void
    {
        $this->assertBlockedBy(Game::factory()->draft()->create(['title' => [], 'slug' => 'untitled-x']), 'English title is missing');
    }

    public function test_publish_sets_status_and_published_at(): void
    {
        $this->freezeSecond();
        $game = Game::factory()->draft()->create();

        app(GamePublisher::class)->publish($game);

        $game->refresh();
        $this->assertSame(GameStatus::Published, $game->status);
        $this->assertTrue($game->published_at->equalTo(now()));
        $this->assertTrue($game->isPublic());
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.publish', 'subject_type' => 'Game', 'subject_id' => $game->id]);
    }

    public function test_publish_throws_with_blockers_and_leaves_game_hidden(): void
    {
        $game = Game::factory()->draft()->unverified()->create(['license_type' => null]);

        try {
            app(GamePublisher::class)->publish($game);
            $this->fail('Expected PublishException');
        } catch (PublishException $e) {
            $this->assertCount(2, $e->blockers);
        }
        $this->assertSame(GameStatus::Draft, $game->fresh()->status);
        $this->assertFalse($game->isPublic());
    }

    public function test_scheduled_publish(): void
    {
        $game = Game::factory()->draft()->create();
        $at = now()->addDays(2)->startOfMinute();

        app(GamePublisher::class)->publish($game, $at);

        $game->refresh();
        $this->assertSame(GameStatus::Published, $game->status);
        $this->assertTrue($game->published_at->equalTo($at));
        $this->assertFalse($game->isPublic());

        $this->travelTo($at->copy()->addMinute());
        $this->assertTrue($game->isPublic());
    }

    public function test_republishing_keeps_an_existing_future_schedule(): void
    {
        $at = now()->addDay()->startOfMinute();
        $game = Game::factory()->draft()->create(['published_at' => $at]);

        app(GamePublisher::class)->publish($game);

        $this->assertTrue($game->fresh()->published_at->equalTo($at));
    }

    public function test_unpublish_hides_game(): void
    {
        $game = Game::factory()->create();
        app(GamePublisher::class)->unpublish($game);
        $this->assertSame(GameStatus::Unpublished, $game->fresh()->status);
        $this->assertFalse($game->isPublic());
    }

    public function test_provider_allow_list_matching(): void
    {
        $p = Provider::factory()->make(['allowed_embed_hosts' => ['games.example.com', '*.cdn.example.net']]);
        $this->assertTrue($p->allowsEmbedHost('games.example.com'));
        $this->assertTrue($p->allowsEmbedHost('GAMES.EXAMPLE.COM'));
        $this->assertTrue($p->allowsEmbedHost('eu.cdn.example.net'));
        $this->assertFalse($p->allowsEmbedHost('cdn.example.net'));
        $this->assertFalse($p->allowsEmbedHost('evilgames.example.com'));
        $this->assertFalse($p->allowsEmbedHost('games.example.com.evil.io'));
    }
}
