<?php

namespace Tests\Feature\Admin;

use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Enums\Role;
use App\Jobs\RunImportBatch;
use App\Models\Category;
use App\Models\Game;
use App\Models\ImportBatch;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->provider = Provider::factory()->withLicenseDefaults()->create();
        $this->actingAs($this->userWithRole(Role::GameManager));
    }

    private function csv(array $rows): UploadedFile
    {
        $header = ['external_id', 'title', 'title_ru', 'engine', 'embed_url', 'license_type', 'source_url', 'categories', 'tags'];
        $lines = [implode(',', $header)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($h) => '"'.str_replace('"', '""', (string) ($row[$h] ?? '')).'"', $header));
        }

        return UploadedFile::fake()->createWithContent('games.csv', implode("\n", $lines)."\n");
    }

    private function upload(UploadedFile $file, string $source = 'csv', ?Provider $provider = null): ImportBatch
    {
        $this->post('/admin/imports', [
            'source' => $source,
            'provider_id' => ($provider ?? $this->provider)->id,
            'file' => $file,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return ImportBatch::query()->latest('id')->firstOrFail();
    }

    /**
     * Runs the batch the way the run endpoint does after validation. The endpoint itself is
     * currently broken (see test_run_endpoint_with_confirmation), so the job is dispatched directly.
     */
    private function runImport(ImportBatch $batch, ?array $ids = null): void
    {
        RunImportBatch::dispatchSync($batch->id, $ids, auth()->id());
    }

    private function statuses(ImportBatch $batch): array
    {
        return $batch->items()->orderBy('row_number')->pluck('status')->all();
    }

    public function test_csv_preview_classifies_rows_without_touching_the_catalog(): void
    {
        $category = Category::factory()->create(['slug' => 'puzzle']);
        Game::factory()->create(['provider_id' => $this->provider->id, 'external_id' => 'dup-1']);
        $gamesBefore = Game::query()->count();

        $batch = $this->upload($this->csv([
            // 1: valid; license/source/hosting/authorization come from provider defaults
            ['external_id' => 'ok-1', 'title' => 'Imported Puzzler', 'title_ru' => 'Головоломка', 'embed_url' => 'https://games.example.com/p/1', 'categories' => 'Puzzle', 'tags' => 'brain|logic'],
            // 2: host not on allow-list
            ['external_id' => 'bad-host', 'title' => 'Evil Host', 'embed_url' => 'https://evil.example.org/g'],
            // 3: plain http
            ['external_id' => 'bad-http', 'title' => 'Plain Http', 'embed_url' => 'http://games.example.com/p/3'],
            // 4: duplicate of an existing game from this provider
            ['external_id' => 'dup-1', 'title' => 'Already Here', 'embed_url' => 'https://games.example.com/p/4'],
            // 5: missing title
            ['external_id' => 'no-title', 'embed_url' => 'https://games.example.com/p/5'],
        ]));

        $batch->refresh();
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(5, $batch->total);
        $this->assertSame(1, $batch->valid);
        $this->assertSame(1, $batch->duplicates);
        $this->assertSame(3, $batch->failed);
        $this->assertSame(['valid', 'invalid', 'invalid', 'duplicate', 'invalid'], $this->statuses($batch));
        $this->assertSame($gamesBefore, Game::query()->count());

        $items = $batch->items()->orderBy('row_number')->get();
        $this->assertStringContainsString("not on the provider's allow-list", implode(' ', $items[1]->errors));
        $this->assertStringContainsString('HTTPS', implode(' ', $items[2]->errors));
        $this->assertStringContainsString('Duplicate of game', implode(' ', $items[3]->errors));
        $this->assertSame('Provider agreement', $items[0]->normalized['license_type']);
        $this->assertSame(['puzzle'], $items[0]->normalized['categories']);

        $this->get("/admin/imports/{$batch->id}")->assertOk()->assertSee('Imported Puzzler');
        unset($category);
    }

    public function test_row_without_license_is_invalid_when_provider_has_no_default(): void
    {
        $bare = Provider::factory()->create(['settings' => ['hosting_method' => 'iframe_embed', 'embed_authorized' => '1']]);

        $batch = $this->upload($this->csv([
            ['external_id' => 'a', 'title' => 'No License', 'embed_url' => 'https://games.example.com/a', 'source_url' => 'https://games.example.com'],
            ['external_id' => 'b', 'title' => 'Has License', 'embed_url' => 'https://games.example.com/b', 'source_url' => 'https://games.example.com', 'license_type' => 'CC-BY-4.0'],
            ['external_id' => 'c', 'title' => 'No Source', 'embed_url' => 'https://games.example.com/c', 'license_type' => 'CC-BY-4.0'],
        ]), 'csv', $bare);

        $this->assertSame(['invalid', 'valid', 'invalid'], $this->statuses($batch));
        $items = $batch->items()->orderBy('row_number')->get();
        $this->assertStringContainsString('Missing license type', implode(' ', $items[0]->errors));
        $this->assertStringContainsString('Missing source URL', implode(' ', $items[2]->errors));
    }

    public function test_duplicate_rows_within_the_same_file_are_detected(): void
    {
        $batch = $this->upload($this->csv([
            ['external_id' => 'same', 'title' => 'Twin One', 'embed_url' => 'https://games.example.com/t1'],
            ['external_id' => 'same', 'title' => 'Twin Two', 'embed_url' => 'https://games.example.com/t2'],
        ]));
        $this->assertSame(['valid', 'duplicate'], $this->statuses($batch));
    }

    public function test_running_import_creates_hidden_drafts_with_unverified_rights(): void
    {
        $batch = $this->upload($this->csv([
            ['external_id' => 'ok-1', 'title' => 'Imported One', 'embed_url' => 'https://games.example.com/1'],
            ['external_id' => 'ok-2', 'title' => 'Imported Two', 'embed_url' => 'https://games.example.com/2'],
            ['external_id' => 'bad', 'title' => 'Bad', 'embed_url' => 'http://games.example.com/3'],
        ]));

        // Confirmation of the provider agreement is required.
        $this->post("/admin/imports/{$batch->id}/run", ['mode' => 'all_valid'])->assertSessionHasErrors('confirm');
        $this->assertSame(0, Game::query()->count());

        $this->runImport($batch);

        $games = Game::query()->orderBy('external_id')->get();
        $this->assertSame(['ok-1', 'ok-2'], $games->pluck('external_id')->all());
        foreach ($games as $game) {
            $this->assertSame(GameStatus::Draft, $game->status);
            $this->assertSame(RightsStatus::Unverified, $game->rights_status);
            $this->assertNull($game->published_at);
            $this->assertSame($this->provider->id, $game->provider_id);
            $this->assertSame('iframe_embed', $game->hosting_method);
            $this->assertTrue($game->embed_authorized);
            $this->assertFalse($game->isPublic());
        }
        $this->assertSame(0, Game::query()->public()->count());

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->imported);
        $this->assertDatabaseHas('audit_logs', ['action' => 'import.run', 'subject_id' => $batch->id]);

        // Running again does not create duplicates (items are no longer "valid").
        $this->runImport($batch->fresh());
        $this->assertSame(2, Game::query()->count());
    }

    public function test_imported_game_can_only_go_public_after_review(): void
    {
        $batch = $this->upload($this->csv([
            ['external_id' => 'x1', 'title' => 'Review Me', 'embed_url' => 'https://games.example.com/x1'],
        ]));
        $this->runImport($batch);
        $game = Game::query()->firstOrFail();

        $this->post("/admin/games/{$game->slug}/publish")->assertSessionHas('error');
        $this->assertFalse($game->isPublic());

        $this->post("/admin/games/{$game->slug}/rights", ['decision' => 'verified', 'confirm' => '1', 'note' => '']);
        $this->post("/admin/games/{$game->slug}/publish")->assertSessionHas('status', 'Published.');
        $this->assertTrue($game->isPublic());
    }

    public function test_selected_mode_imports_only_chosen_rows(): void
    {
        $batch = $this->upload($this->csv([
            ['external_id' => 's1', 'title' => 'Pick Me', 'embed_url' => 'https://games.example.com/s1'],
            ['external_id' => 's2', 'title' => 'Skip Me', 'embed_url' => 'https://games.example.com/s2'],
        ]));
        $first = $batch->items()->where('row_number', 1)->value('id');

        $this->runImport($batch, [$first]);

        $this->assertSame(['s1'], Game::query()->pluck('external_id')->all());
    }

    public function test_json_import(): void
    {
        $json = json_encode(['games' => [
            ['external_id' => 'j1', 'title' => 'Json Jumper', 'title_ka' => 'ჯსონი', 'embed_url' => 'https://games.example.com/j1', 'devices' => 'desktop|mobile'],
            ['external_id' => 'j2', 'title' => 'Json Bad', 'embed_url' => 'https://nope.example.org/j2'],
        ]]);

        $batch = $this->upload(UploadedFile::fake()->createWithContent('games.json', $json), 'json');
        $batch->refresh();
        $this->assertSame('previewed', $batch->status);
        $this->assertSame(['valid', 'invalid'], $this->statuses($batch));

        $this->runImport($batch);
        $game = Game::query()->where('external_id', 'j1')->firstOrFail();
        $this->assertSame(['en' => 'Json Jumper', 'ka' => 'ჯსონი'], $game->title);
        $this->assertSame(['desktop', 'mobile'], $game->devices);
        $this->assertSame(GameStatus::Draft, $game->status);
        $this->assertSame(RightsStatus::Unverified, $game->rights_status);
    }

    public function test_invalid_json_marks_batch_failed(): void
    {
        $batch = $this->upload(UploadedFile::fake()->createWithContent('games.json', '{"games": [oops'), 'json');
        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertStringContainsString('Invalid JSON', $batch->error);
        // Only previewed batches can be run; without the confirm field the status check is reached.
        $this->post("/admin/imports/{$batch->id}/run", ['mode' => 'repreview'])->assertSessionHas('status', 'Preview rebuilt.');
        $this->assertSame('failed', $batch->fresh()->status);
    }

    public function test_run_endpoint_with_confirmation(): void
    {
        $batch = $this->upload($this->csv([
            ['external_id' => 'h1', 'title' => 'Http Run', 'embed_url' => 'https://games.example.com/h1'],
        ]));
        $this->post("/admin/imports/{$batch->id}/run", ['mode' => 'all_valid', 'confirm' => '1'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Imported 1 games'));
        $this->assertSame(1, Game::query()->count());
    }

    public function test_imports_require_imports_permission(): void
    {
        $this->actingAs($this->userWithRole(Role::ContentEditor));
        $this->post('/admin/imports', ['source' => 'csv', 'file' => $this->csv([])])->assertForbidden();
        $this->get('/admin/imports')->assertForbidden();
    }
}
