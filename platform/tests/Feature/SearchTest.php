<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Game;
use App\Models\Tag;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function titles(string $query): array
    {
        return app(SearchService::class)->search($query)['games']->getCollection()->map(fn (Game $g) => $g->tr('title', 'en'))->all();
    }

    public function test_normalize_and_tokens(): void
    {
        $this->assertSame('snake 2 game', SearchService::normalize('  Snake!!  2   "game"? '));
        $this->assertSame(['tic', 'tac', 'toe'], SearchService::tokens('tic-tac-toe'));
        $this->assertSame('', SearchService::normalize(null));
    }

    public function test_finds_game_by_english_title(): void
    {
        Game::factory()->title('Snake Classic')->create();
        Game::factory()->title('Tetra Blocks')->create();

        $this->assertSame(['Snake Classic'], $this->titles('snake'));
    }

    public function test_finds_game_by_title_in_another_locale(): void
    {
        Game::factory()->title('Snake Classic', ['ru' => 'Змейка', 'ka' => 'გველი', 'tr' => 'Yılan Oyunu'])->create();

        $this->assertSame(['Snake Classic'], $this->titles('змейка'));
        $this->assertSame(['Snake Classic'], $this->titles('ЗМЕЙКА'));
        $this->assertSame(['Snake Classic'], $this->titles('გველი'));
        $this->assertSame(['Snake Classic'], $this->titles('yılan'));
    }

    public function test_finds_game_by_tag_and_category(): void
    {
        $game = Game::factory()->title('Orbital Drift')->create();
        $game->tags()->attach(Tag::factory()->create(['slug' => 'space', 'name' => ['en' => 'Space']]));
        $game->categories()->attach(Category::factory()->create(['name' => ['en' => 'Racing']])->id);
        $game->refreshSearchText();

        $this->assertSame(['Orbital Drift'], $this->titles('space'));
        $this->assertSame(['Orbital Drift'], $this->titles('racing'));
    }

    public function test_typo_tolerance(): void
    {
        Game::factory()->title('Snake Classic')->create();
        Game::factory()->title('Tetra Blocks')->create();

        $result = app(SearchService::class)->search('snske');
        $this->assertSame(['Snake Classic'], $result['games']->getCollection()->map(fn ($g) => $g->tr('title'))->all());
        $this->assertSame('snake', $result['corrected']);

        // Prefix also matches without correction.
        $this->assertSame(['Snake Classic'], $this->titles('snak'));
        // Longer words tolerate two edits.
        $this->assertSame(['Snake Classic'], $this->titles('clasic'));
    }

    public function test_exact_title_ranks_first(): void
    {
        Game::factory()->title('Snake Arena Deluxe')->create(['play_count' => 0]);
        Game::factory()->title('Snake')->create(['play_count' => 0]);
        Game::factory()->title('Super Snake')->create(['play_count' => 0]);

        $this->assertSame('Snake', $this->titles('snake')[0]);
    }

    public function test_hidden_games_never_appear(): void
    {
        Game::factory()->title('Snake Visible')->create();
        Game::factory()->title('Snake Draft')->draft()->create();
        Game::factory()->title('Snake Unverified')->unverified()->create();
        Game::factory()->title('Snake Scheduled')->scheduled()->create();
        Game::factory()->title('Snake Broken')->create(['launch_status' => 'failed']);

        $this->assertSame(['Snake Visible'], $this->titles('snake'));
        // Typo correction must not leak words from hidden games either.
        $this->assertSame([], $this->titles('dradt'));

        $this->get('/en/search?q=snake')->assertOk()->assertSee('Snake Visible')->assertDontSee('Snake Draft')->assertDontSee('Snake Unverified');
        $this->getJson('/api/search/suggest?q=snake')->assertOk()->assertJsonCount(1, 'games');
    }

    public function test_suggest_endpoint_returns_json(): void
    {
        $game = Game::factory()->title('Snake Classic', ['ru' => 'Змейка'])->create();
        Category::factory()->create(['slug' => 'snakes', 'name' => ['en' => 'Snake games']]);

        $this->getJson('/api/search/suggest?q=snake')
            ->assertOk()
            ->assertJsonPath('games.0.title', 'Snake Classic')
            ->assertJsonPath('games.0.url', url('/en/game/'.$game->slug))
            ->assertJsonPath('categories.0.title', 'Snake games');

        $this->getJson('/api/search/suggest?q=s')->assertOk()->assertExactJson(['games' => [], 'categories' => []]);
    }

    public function test_search_page_logs_query(): void
    {
        Game::factory()->title('Snake Classic')->create();
        $this->get('/en/search?q=Snake')->assertOk()->assertSee('Snake Classic');
        $this->assertDatabaseHas('search_queries', ['query' => 'snake', 'locale' => 'en', 'results_count' => 1]);
    }

    public function test_like_wildcards_are_escaped(): void
    {
        Game::factory()->title('Snake Classic')->create();
        $this->assertSame([], $this->titles('%'));
        $this->assertSame([], $this->titles('_'));
    }
}
