<?php

namespace Tests\Feature;

use App\Enums\FlashCompatibility;
use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function publicIds(): array
    {
        return Game::query()->public()->pluck('id')->all();
    }

    public function test_fully_released_game_is_public(): void
    {
        $game = Game::factory()->create();
        $this->assertSame([$game->id], $this->publicIds());
        $this->assertTrue($game->isPublic());
    }

    public function test_draft_and_non_published_statuses_are_hidden(): void
    {
        foreach ([GameStatus::Draft, GameStatus::Pending, GameStatus::Unpublished, GameStatus::Archived] as $status) {
            Game::factory()->create(['status' => $status]);
        }
        $this->assertSame([], $this->publicIds());
    }

    public function test_unverified_and_rejected_rights_are_hidden(): void
    {
        Game::factory()->unverified()->create();
        Game::factory()->create(['rights_status' => RightsStatus::Rejected]);
        $this->assertSame([], $this->publicIds());
    }

    public function test_scheduled_and_missing_release_time_are_hidden(): void
    {
        Game::factory()->scheduled()->create();
        Game::factory()->create(['published_at' => null]);
        $this->assertSame([], $this->publicIds());
    }

    public function test_scheduled_game_becomes_public_once_release_time_passes(): void
    {
        $game = Game::factory()->create(['published_at' => now()->addHour()]);
        $this->assertFalse($game->isPublic());
        $this->travel(2)->hours();
        $this->assertTrue($game->isPublic());
    }

    public function test_failed_launch_check_hides_game(): void
    {
        Game::factory()->create(['launch_status' => 'failed']);
        $untested = Game::factory()->create(['launch_status' => 'untested']);
        $this->assertSame([$untested->id], $this->publicIds());
    }

    public function test_ruffle_games_require_compatible_or_partial_flash_support(): void
    {
        $visible = [];
        foreach (FlashCompatibility::cases() as $compat) {
            $game = Game::factory()->ruffle($compat)->create();
            if (in_array($compat, [FlashCompatibility::Compatible, FlashCompatibility::Partial], true)) {
                $visible[] = $game->id;
            }
        }
        Game::factory()->ruffle()->create(['flash_compatibility' => null]);

        $this->assertEqualsCanonicalizing($visible, $this->publicIds());
    }

    public function test_soft_deleted_game_is_hidden(): void
    {
        Game::factory()->create()->delete();
        $this->assertSame([], $this->publicIds());
    }

    public function test_public_game_page_is_ok(): void
    {
        $game = Game::factory()->title('Star Jumper')->create();
        $this->get('/en/game/'.$game->slug)->assertOk()->assertSee('Star Jumper');
    }

    public function test_hidden_game_pages_are_404_for_visitors(): void
    {
        foreach ([
            Game::factory()->draft()->create(),
            Game::factory()->unverified()->create(),
            Game::factory()->scheduled()->create(),
            Game::factory()->ruffle(FlashCompatibility::Untested)->create(),
        ] as $game) {
            $this->get('/en/game/'.$game->slug)->assertNotFound();
        }
        $this->get('/en/game/does-not-exist')->assertNotFound();
    }

    public function test_category_page_lists_only_public_games(): void
    {
        $category = Category::factory()->create();
        $shown = Game::factory()->title('Visible Voyager')->create();
        $draft = Game::factory()->title('Draft Dragon')->draft()->create();
        $unverified = Game::factory()->title('Unverified Unicorn')->unverified()->create();
        foreach ([$shown, $draft, $unverified] as $g) {
            $g->categories()->attach($category->id, ['is_primary' => true]);
        }

        $this->get('/en/category/'.$category->slug)
            ->assertOk()
            ->assertSee('Visible Voyager')
            ->assertDontSee('Draft Dragon')
            ->assertDontSee('Unverified Unicorn');
    }

    public function test_inactive_category_is_404(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        $this->get('/en/category/'.$category->slug)->assertNotFound();
    }

    public function test_games_index_lists_only_public_games(): void
    {
        Game::factory()->title('Listed Llama')->create();
        Game::factory()->title('Secret Squirrel')->draft()->create();

        $this->get('/en/games')->assertOk()->assertSee('Listed Llama')->assertDontSee('Secret Squirrel');
    }
}
