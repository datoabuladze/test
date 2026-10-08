<?php

namespace Tests\Feature\Admin;

use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGameFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->userWithRole(Role::GameManager);
        $this->actingAs($this->manager);
    }

    /** A complete, valid form payload for an existing game. */
    private function formFor(Game $game, array $overrides = []): array
    {
        return array_merge([
            'title' => $game->title,
            'slug' => $game->slug,
            'engine' => $game->engine->value,
            'entry_path' => $game->entry_path,
            'embed_url' => $game->embed_url,
            'orientation' => $game->orientation,
            'provider_id' => $game->provider_id,
            'source_url' => $game->source_url,
            'license_type' => $game->license_type,
            'license_url' => $game->license_url,
            'hosting_method' => $game->hosting_method,
            'commercial_use_allowed' => $game->commercial_use_allowed ? '1' : null,
            'ads_allowed' => $game->ads_allowed ? '1' : null,
            'modifications_allowed' => $game->modifications_allowed ? '1' : null,
            'thumbnail_rights' => $game->thumbnail_rights ? '1' : null,
            'embed_authorized' => $game->embed_authorized ? '1' : null,
            'score_mode' => $game->score_mode,
        ], $overrides);
    }

    public function test_admin_pages_render(): void
    {
        $game = Game::factory()->create();
        $this->get('/admin/games')->assertOk()->assertSee($game->tr('title'));
        $this->get('/admin/games/create')->assertOk();
        $this->get("/admin/games/{$game->slug}/edit")->assertOk();
        $this->get("/admin/games/{$game->slug}/preview")->assertOk();
    }

    public function test_store_creates_a_draft_with_unverified_rights(): void
    {
        $category = Category::factory()->create();

        $response = $this->post('/admin/games', [
            'title' => ['en' => 'Brand New Game', 'ka' => 'ახალი თამაში'],
            'engine' => 'html5',
            'orientation' => 'any',
            'score_mode' => 'none',
            'license_type' => 'MIT',
            'source_url' => 'https://example.org/src',
            'hosting_method' => 'self_hosted',
            'categories' => [$category->id],
            'tags' => 'arcade, retro',
            // An attempt to smuggle a status in is ignored.
            'status' => 'published',
            'rights_status' => 'verified',
        ]);

        $game = Game::query()->where('slug', 'brand-new-game')->firstOrFail();
        $response->assertRedirect("/admin/games/{$game->slug}/edit");
        $this->assertSame(GameStatus::Draft, $game->status);
        $this->assertSame(RightsStatus::Unverified, $game->rights_status);
        $this->assertNull($game->published_at);
        $this->assertFalse($game->isPublic());
        $this->assertSame(['en' => 'Brand New Game', 'ka' => 'ახალი თამაში'], $game->title);
        $this->assertSame([$category->id], $game->categories()->pluck('categories.id')->all());
        $this->assertEqualsCanonicalizing(['arcade', 'retro'], $game->tags()->pluck('slug')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.create', 'subject_type' => 'Game', 'subject_id' => $game->id, 'user_id' => $this->manager->id]);
    }

    public function test_store_validates_input(): void
    {
        $this->post('/admin/games', [
            'title' => ['en' => ''], 'engine' => 'cobol', 'orientation' => 'any', 'score_mode' => 'none',
            'entry_path' => 'games/../../.env', 'embed_url' => 'http://insecure.example.com',
        ])->assertSessionHasErrors(['title.en', 'engine', 'entry_path', 'embed_url']);
        $this->assertSame(0, Game::query()->count());
    }

    public function test_rights_verification_requires_confirmation(): void
    {
        $game = Game::factory()->draft()->unverified()->create();

        $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'verified', 'note' => ''])->assertSessionHasErrors('confirm');
        $this->assertSame(RightsStatus::Unverified, $game->fresh()->rights_status);

        $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'verified', 'confirm' => '1', 'note' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $game->refresh();
        $this->assertSame(RightsStatus::Verified, $game->rights_status);
        $this->assertSame($this->manager->id, $game->rights_verified_by);
        $this->assertNotNull($game->rights_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.rights.verified', 'subject_id' => $game->id]);
    }

    public function test_rights_verification_requires_license_and_source(): void
    {
        $noLicense = Game::factory()->draft()->unverified()->create(['license_type' => null]);
        $noSource = Game::factory()->draft()->unverified()->create(['source_url' => null]);

        foreach ([$noLicense, $noSource] as $game) {
            $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'verified', 'confirm' => '1', 'note' => ''])
                ->assertSessionHas('error');
            $this->assertSame(RightsStatus::Unverified, $game->fresh()->rights_status);
        }

        // Originals need a license but no external source.
        $original = Game::factory()->original()->draft()->unverified()->create();
        $this->post("/admin/games/{$original->slug}/rights", ['decision' => 'verified', 'confirm' => '1', 'note' => '']);
        $this->assertSame(RightsStatus::Verified, $original->fresh()->rights_status);
    }

    public function test_rights_decision_without_note_field(): void
    {
        $game = Game::factory()->draft()->unverified()->create();
        $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'verified', 'confirm' => '1'])->assertRedirect();
        $this->assertSame(RightsStatus::Verified, $game->fresh()->rights_status);
    }

    public function test_rejecting_rights_unpublishes_a_live_game(): void
    {
        $game = Game::factory()->create();

        $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'rejected', 'note' => 'License withdrawn']);

        $game->refresh();
        $this->assertSame(RightsStatus::Rejected, $game->rights_status);
        $this->assertSame(GameStatus::Unpublished, $game->status);
        $this->assertStringContainsString('License withdrawn', $game->license_notes);
        $this->assertFalse($game->isPublic());
    }

    public function test_changing_license_fields_on_verified_game_resets_rights(): void
    {
        $game = Game::factory()->create();

        $this->put("/admin/games/{$game->slug}", $this->formFor($game, ['license_type' => 'CC-BY-4.0']))
            ->assertSessionHasNoErrors();

        $game->refresh();
        $this->assertSame('CC-BY-4.0', $game->license_type);
        $this->assertSame(RightsStatus::Unverified, $game->rights_status);
        $this->assertNull($game->rights_verified_at);
        $this->assertFalse($game->isPublic());
        $log = AuditLog::query()->where('action', 'game.update')->where('subject_id', $game->id)->firstOrFail();
        $this->assertTrue($log->meta['rights_reset']);
    }

    public function test_changing_source_or_embed_flags_also_resets_rights(): void
    {
        $game = Game::factory()->create();
        $this->put("/admin/games/{$game->slug}", $this->formFor($game, ['source_url' => 'https://elsewhere.example.org/x']));
        $this->assertSame(RightsStatus::Unverified, $game->fresh()->rights_status);

        $game2 = Game::factory()->create();
        $this->put("/admin/games/{$game2->slug}", $this->formFor($game2, ['ads_allowed' => null]));
        $this->assertSame(RightsStatus::Unverified, $game2->fresh()->rights_status);
    }

    public function test_editing_non_license_fields_keeps_rights_verified(): void
    {
        $game = Game::factory()->create();

        $this->put("/admin/games/{$game->slug}", $this->formFor($game, [
            'title' => ['en' => 'Renamed Game'],
            'short_description' => ['en' => 'New blurb'],
        ]))->assertSessionHasNoErrors();

        $game->refresh();
        $this->assertSame('Renamed Game', $game->tr('title'));
        $this->assertSame(RightsStatus::Verified, $game->rights_status);
        $this->assertTrue($game->isPublic());
    }

    public function test_publish_endpoint_refuses_when_blockers_exist(): void
    {
        $game = Game::factory()->draft()->unverified()->create(['entry_path' => null]);

        $this->post("/admin/games/{$game->slug}/publish")
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Cannot publish')
                && str_contains($msg, 'rights have not been verified') && str_contains($msg, 'entry path'));

        $this->assertSame(GameStatus::Draft, $game->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'game.publish']);
    }

    public function test_publish_endpoint_supports_scheduling(): void
    {
        $game = Game::factory()->draft()->create();
        $at = now()->addDays(5)->startOfMinute();

        $this->post("/admin/games/{$game->slug}/publish", ['publish_at' => $at->toDateTimeString()])
            ->assertSessionHas('status', fn ($m) => str_starts_with($m, 'Scheduled for'));

        $game->refresh();
        $this->assertSame(GameStatus::Published, $game->status);
        $this->assertTrue($game->published_at->equalTo($at));
        $this->assertFalse($game->isPublic());
    }

    public function test_unpublish_endpoint(): void
    {
        $game = Game::factory()->create();
        $this->post("/admin/games/{$game->slug}/unpublish")->assertSessionHas('status', 'Unpublished.');
        $this->assertFalse($game->isPublic());
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.unpublish', 'subject_id' => $game->id]);
    }

    public function test_bulk_publish_reports_failures(): void
    {
        $ready = Game::factory()->title('Ready Rocket')->draft()->create();
        $blocked = Game::factory()->title('Blocked Badger')->draft()->unverified()->create();

        $this->post('/admin/games/bulk', ['ids' => [$ready->id, $blocked->id], 'action' => 'publish'])
            ->assertRedirect()
            ->assertSessionHas('status', 'publish: 1 of 2 games updated.')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Blocked Badger') && str_contains($m, 'rights have not been verified')
                && ! str_contains($m, 'Ready Rocket'));

        $this->assertTrue($ready->isPublic());
        $this->assertFalse($blocked->isPublic());
        $log = AuditLog::query()->where('action', 'game.bulk.publish')->firstOrFail();
        $this->assertSame(['ids' => [$ready->id, $blocked->id], 'ok' => 1, 'failed' => 1], $log->meta);
    }

    public function test_bulk_publish_requires_publish_permission(): void
    {
        $game = Game::factory()->draft()->create();
        // A role with games.manage but without games.publish does not exist by default, so
        // verify the permission check through a content editor (no games.* access at all).
        $this->actingAs($this->userWithRole(Role::ContentEditor))
            ->post('/admin/games/bulk', ['ids' => [$game->id], 'action' => 'publish'])->assertForbidden();
        $this->assertFalse($game->isPublic());
    }

    public function test_trash_and_restore(): void
    {
        $game = Game::factory()->create();

        $this->delete("/admin/games/{$game->slug}")->assertRedirect('/admin/games');
        $this->assertSoftDeleted('games', ['id' => $game->id]);
        $trashed = Game::withTrashed()->find($game->id);
        $this->assertSame(GameStatus::Archived, $trashed->status);
        $this->get('/en/game/'.$game->slug)->assertNotFound();

        $this->post("/admin/games/{$game->slug}/restore")->assertRedirect("/admin/games/{$game->slug}/edit");
        $restored = Game::query()->findOrFail($game->id);
        $this->assertSame(GameStatus::Draft, $restored->status);
        $this->assertFalse($restored->isPublic());

        $this->assertSame(['game.delete', 'game.restore'], AuditLog::query()->where('subject_id', $game->id)
            ->where('subject_type', 'Game')->orderBy('id')->pluck('action')->all());
    }

    public function test_force_delete_of_trashed_game(): void
    {
        $game = Game::factory()->create();
        $game->delete();

        $this->delete("/admin/games/{$game->slug}")->assertRedirect('/admin/games?trashed=1');
        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.force_delete', 'subject_id' => $game->id]);
    }

    public function test_bulk_trash(): void
    {
        $a = Game::factory()->create();
        $b = Game::factory()->create();
        $this->post('/admin/games/bulk', ['ids' => [$a->id, $b->id], 'action' => 'trash'])
            ->assertSessionHas('status', 'trash: 2 of 2 games updated.');
        $this->assertSoftDeleted('games', ['id' => $a->id]);
        $this->assertSoftDeleted('games', ['id' => $b->id]);
    }
}
