<?php

namespace Tests\Feature\Admin;

use App\Enums\FlashCompatibility;
use App\Enums\GameEngine;
use App\Enums\GameStatus;
use App\Enums\Role;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class GamePackageUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpDir;

    private bool $gameFilesExisted;

    /** @var list<string> */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/pkgtest-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);
        $this->gameFilesExisted = is_dir(public_path('game-files'));
        $this->before = $this->gameFileDirs();
        $this->actingAs($this->userWithRole(Role::GameManager));
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->gameFileDirs(), $this->before) as $dir) {
            if (preg_match('#/\d+-[a-z0-9]{12}$#', $dir)) {
                File::deleteDirectory($dir);
            }
        }
        if (! $this->gameFilesExisted && is_dir(public_path('game-files')) && ! (new \FilesystemIterator(public_path('game-files')))->valid()) {
            @rmdir(public_path('game-files'));
        }
        File::deleteDirectory($this->tmpDir);
        parent::tearDown();
    }

    /** @return list<string> */
    private function gameFileDirs(): array
    {
        return is_dir(public_path('game-files')) ? (glob(public_path('game-files').'/*', GLOB_ONLYDIR) ?: []) : [];
    }

    /** @param array<string, string> $files name => contents */
    private function zip(array $files, string $name = 'game.zip'): UploadedFile
    {
        $path = $this->tmpDir.'/'.bin2hex(random_bytes(4)).'-'.$name;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function upload(Game $game, UploadedFile $file)
    {
        return $this->from("/admin/games/{$game->slug}/edit")
            ->post("/admin/games/{$game->slug}/package", ['package' => $file]);
    }

    public function test_valid_zip_installs_and_sets_entry_path(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null, 'launch_status' => 'ok']);

        $this->upload($game, $this->zip([
            'index.html' => '<!doctype html><title>t</title><script src="js/main.js"></script>',
            'js/main.js' => 'console.log(1)',
            'assets/sprite.png' => 'png',
        ]))->assertRedirect("/admin/games/{$game->slug}/edit")->assertSessionHasNoErrors();

        $game->refresh();
        $this->assertMatchesRegularExpression('#^game-files/'.$game->id.'-[a-z0-9]{12}/index\.html$#', $game->entry_path);
        $this->assertSame(GameEngine::Html5, $game->engine);
        $this->assertSame('self_hosted', $game->hosting_method);
        $this->assertSame('untested', $game->launch_status);
        $this->assertFileExists(public_path($game->entry_path));
        $this->assertFileExists(public_path(dirname($game->entry_path).'/js/main.js'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.package', 'subject_id' => $game->id]);
    }

    public function test_new_package_for_a_published_game_takes_it_offline(): void
    {
        $game = Game::factory()->create(['launch_status' => 'ok']);
        $this->assertTrue(Game::query()->public()->whereKey($game->id)->exists());

        $this->upload($game, $this->zip(['index.html' => '<!doctype html><title>t</title>']))
            ->assertSessionHasNoErrors();

        $this->assertSame(GameStatus::Unpublished, $game->refresh()->status);
        $this->assertFalse(Game::query()->public()->whereKey($game->id)->exists());
    }

    public function test_zip_with_single_top_level_folder_and_phaser_detection(): void
    {
        $game = Game::factory()->draft()->create();

        $this->upload($game, $this->zip([
            'mygame/index.html' => '<script src="phaser.min.js"></script>',
            'mygame/phaser.min.js' => '/* Phaser v3 */',
        ]))->assertSessionHasNoErrors();

        $game->refresh();
        $this->assertStringEndsWith('/mygame/index.html', $game->entry_path);
        $this->assertSame(GameEngine::Phaser, $game->engine);
    }

    public function test_zip_with_path_traversal_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);

        $this->upload($game, $this->zip(['index.html' => 'ok', '../evil.html' => 'pwned']))
            ->assertSessionHasErrors(['package' => 'Unsafe path in package: ../evil.html']);

        $this->assertNull($game->fresh()->entry_path);
        $this->assertSame($this->before, $this->gameFileDirs());
        $this->assertFileDoesNotExist(public_path('evil.html'));
    }

    public function test_zip_with_nested_traversal_or_absolute_path_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);

        $this->upload($game, $this->zip(['index.html' => 'ok', 'a/../../b.js' => 'x']))->assertSessionHasErrors('package');
        $this->upload($game, $this->zip(['index.html' => 'ok', '/etc/x.js' => 'x']))->assertSessionHasErrors('package');
        $this->assertNull($game->fresh()->entry_path);
    }

    public function test_zip_with_php_file_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);

        $this->upload($game, $this->zip(['index.html' => 'ok', 'shell.php' => '<?php system($_GET["c"]);']))
            ->assertSessionHasErrors(['package' => 'File type not allowed in game packages: shell.php']);

        $this->upload($game, $this->zip(['index.html' => 'ok', '.htaccess' => 'AddType application/x-httpd-php .png']))
            ->assertSessionHasErrors('package');
        $this->upload($game, $this->zip(['index.html' => 'ok', 'x.phtml' => '<?php']))->assertSessionHasErrors('package');

        $this->assertNull($game->fresh()->entry_path);
        $this->assertSame($this->before, $this->gameFileDirs());
    }

    public function test_zip_without_index_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);
        $this->upload($game, $this->zip(['main.js' => 'x', 'other/index.html' => 'x', 'second/a.js' => 'y']))
            ->assertSessionHasErrors('package');
        $this->assertNull($game->fresh()->entry_path);
    }

    public function test_non_zip_garbage_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);
        $path = $this->tmpDir.'/bad.zip';
        file_put_contents($path, 'definitely not a zip');

        $this->upload($game, new UploadedFile($path, 'bad.zip', 'application/zip', null, true))->assertSessionHasErrors('package');
    }

    public function test_disallowed_upload_extension_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);
        $path = $this->tmpDir.'/game.exe';
        file_put_contents($path, 'MZ');

        $this->upload($game, new UploadedFile($path, 'game.exe', null, null, true))->assertSessionHasErrors('package');
    }

    public function test_fake_swf_with_bad_signature_is_rejected(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);
        $path = $this->tmpDir.'/game.swf';
        file_put_contents($path, '<html>not flash</html>');

        $this->upload($game, new UploadedFile($path, 'game.swf', 'application/x-shockwave-flash', null, true))
            ->assertSessionHasErrors(['package' => 'This file is not a valid SWF (bad signature).']);

        $this->assertNull($game->fresh()->entry_path);
        $this->assertSame($this->before, $this->gameFileDirs());
    }

    public function test_valid_swf_installs_as_ruffle_game_with_untested_compatibility(): void
    {
        $game = Game::factory()->draft()->create(['entry_path' => null]);
        $path = $this->tmpDir.'/movie.swf';
        file_put_contents($path, 'FWS'.chr(10).str_repeat("\0", 32));

        $this->upload($game, new UploadedFile($path, 'movie.swf', 'application/x-shockwave-flash', null, true))
            ->assertSessionHasNoErrors();

        $game->refresh();
        $this->assertSame(GameEngine::Ruffle, $game->engine);
        $this->assertSame(FlashCompatibility::Untested, $game->flash_compatibility);
        $this->assertStringEndsWith('/game.swf', $game->entry_path);
        $this->assertSame(10, $game->engine_config['swf_version']);
        $this->assertFileExists(public_path($game->entry_path));
        // Untested Flash cannot be public even if everything else is fine.
        $this->assertFalse($game->isPublic());
    }

    public function test_package_upload_requires_games_manage(): void
    {
        $game = Game::factory()->draft()->create();
        $this->actingAs($this->userWithRole(Role::ContentEditor));
        $this->upload($game, $this->zip(['index.html' => 'x']))->assertForbidden();
    }
}
