<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Game;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_applies_to_unknown_path(): void
    {
        Redirect::query()->create(['from_path' => '/old-page', 'to_url' => '/en', 'status_code' => 301]);
        Cache::forget('redirects.map');

        $this->get('/old-page')->assertStatus(301)->assertRedirect('/en');
        $this->assertSame(1, Redirect::query()->value('hits'));
    }

    public function test_redirect_status_code_is_respected_and_unknown_paths_still_404(): void
    {
        Redirect::query()->create(['from_path' => '/temp', 'to_url' => '/en/games', 'status_code' => 302]);
        Cache::forget('redirects.map');

        $this->get('/temp')->assertStatus(302)->assertRedirect('/en/games');
        $this->get('/not-mapped')->assertNotFound();
        $this->post('/temp')->assertNotFound(); // only GET requests are redirected
    }

    public function test_redirect_applies_to_404s_inside_localized_routes(): void
    {
        $game = Game::factory()->create();
        Redirect::query()->create(['from_path' => '/en/game/retired-game', 'to_url' => $game->url('en'), 'status_code' => 301]);
        Cache::forget('redirects.map');

        $this->get('/en/game/retired-game')->assertStatus(301)->assertRedirect($game->url('en'));
    }

    public function test_redirect_does_not_override_existing_pages(): void
    {
        $game = Game::factory()->create();
        Redirect::query()->create(['from_path' => '/en/game/'.$game->slug, 'to_url' => '/en', 'status_code' => 301]);
        Cache::forget('redirects.map');

        $this->get('/en/game/'.$game->slug)->assertOk();
    }

    public function test_admin_can_create_redirect_via_seo_screen(): void
    {
        $this->actingAs($this->userWithRole(Role::ContentEditor))
            ->post('/admin/redirects', ['from_path' => '/legacy', 'to_url' => '/en/games', 'status_code' => 301])
            ->assertRedirect();

        $this->assertDatabaseHas('redirects', ['from_path' => '/legacy']);
        $this->get('/legacy')->assertStatus(301)->assertRedirect('/en/games');
    }
}
