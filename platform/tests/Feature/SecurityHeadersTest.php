<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\GameController;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_pages_get_nonce_csp_and_frame_protection(): void
    {
        $response = $this->get('/en')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=_-]{16,}'/", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline' 'unsafe-eval'", $csp);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_nonce_is_used_in_page_scripts_and_changes_per_request(): void
    {
        $first = $this->get('/en');
        preg_match("/'nonce-([^']+)'/", $first->headers->get('Content-Security-Policy'), $m1);
        $this->assertStringContainsString('nonce="'.$m1[1].'"', $first->getContent());

        $second = $this->get('/en');
        preg_match("/'nonce-([^']+)'/", $second->headers->get('Content-Security-Policy'), $m2);
        $this->assertNotSame($m1[1], $m2[1]);
    }

    public function test_json_responses_do_not_get_html_csp(): void
    {
        $this->getJson('/api/search/suggest?q=ab')->assertOk()->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_public_original_game_frame_redirects_without_deny(): void
    {
        $game = Game::factory()->original()->create();

        $response = $this->get('/frame/'.$game->slug);

        $response->assertRedirect();
        $this->assertStringContainsString('/games/originals/'.$game->slug.'/index.html?lang=', $response->headers->get('Location'));
        $response->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('nonce-', $response->headers->get('Content-Security-Policy'));
    }

    public function test_non_public_game_frame_is_404_for_guests(): void
    {
        $draft = Game::factory()->original()->draft()->create();
        $this->get('/frame/'.$draft->slug)->assertNotFound();

        $iframe = Game::factory()->iframe()->create();
        $this->get('/frame/'.$iframe->slug)->assertNotFound();
    }

    public function test_signed_preview_url_works_for_draft(): void
    {
        $draft = Game::factory()->draft()->create();

        $url = GameController::frameUrl($draft, preview: true);
        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertRedirect()->assertHeaderMissing('X-Frame-Options');

        // Tampered or expired signatures are refused.
        $this->get($url.'x')->assertNotFound();
        $this->travel(31)->minutes();
        $this->get($url)->assertNotFound();
    }

    public function test_signature_for_one_game_does_not_unlock_another(): void
    {
        $a = Game::factory()->draft()->create();
        $b = Game::factory()->draft()->create();
        $url = GameController::frameUrl($a, preview: true);
        $this->get(str_replace('/frame/'.$a->slug, '/frame/'.$b->slug, $url))->assertNotFound();
    }

    public function test_staff_can_open_draft_frames_without_signature(): void
    {
        $draft = Game::factory()->draft()->create();
        $this->actingAs($this->userWithRole(Role::GameManager))->get('/frame/'.$draft->slug)->assertRedirect();
    }

    public function test_ruffle_frame_renders_wrapper(): void
    {
        $game = Game::factory()->ruffle()->create();
        $response = $this->get('/frame/'.$game->slug)->assertOk();
        $response->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }
}
