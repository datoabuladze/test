<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationSeoTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return [['en'], ['ka'], ['tr'], ['ru']];
    }

    #[DataProvider('locales')]
    public function test_home_renders_in_every_locale(string $locale): void
    {
        $this->get("/$locale")
            ->assertOk()
            ->assertSee('<html lang="'.$locale.'"', false);
        $this->assertSame($locale, app()->getLocale());
    }

    public function test_unknown_locale_is_404(): void
    {
        $this->get('/de')->assertNotFound();
        $this->get('/xx/games')->assertNotFound();
    }

    public function test_root_redirects_to_a_locale(): void
    {
        $this->get('/')->assertRedirect('/en');
        $this->get('/', ['Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.5'])->assertRedirect('/ru');
    }

    public function test_hreflang_alternates_are_present(): void
    {
        $html = $this->get('/en/games')->assertOk()->getContent();
        foreach (['en', 'ka', 'tr', 'ru'] as $code) {
            $this->assertStringContainsString('<link rel="alternate" hreflang="'.$code.'" href="'.url("/$code/games").'">', $html);
        }
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="'.url('/en/games').'">', $html);
    }

    public function test_game_page_hreflang_keeps_the_slug(): void
    {
        $game = Game::factory()->create();
        $html = $this->get('/ka/game/'.$game->slug)->assertOk()->getContent();
        $this->assertStringContainsString('hreflang="tr" href="'.url('/tr/game/'.$game->slug).'"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/en/game/'.$game->slug).'"', $html);
    }

    public function test_sitemap_index_lists_every_locale(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        foreach (['en', 'ka', 'tr', 'ru'] as $locale) {
            $this->assertStringContainsString(url("/sitemaps/$locale/games.xml"), $response->getContent());
        }
    }

    public function test_games_sitemap_contains_public_games_only(): void
    {
        $public = Game::factory()->create();
        $draft = Game::factory()->draft()->create();
        $unverified = Game::factory()->unverified()->create();

        $response = $this->get('/sitemaps/tr/games.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($content));
        $this->assertStringContainsString('<loc>'.url('/tr/game/'.$public->slug).'</loc>', $content);
        $this->assertStringContainsString('hreflang="ka" href="'.url('/ka/game/'.$public->slug).'"', $content);
        $this->assertStringNotContainsString($draft->slug, $content);
        $this->assertStringNotContainsString($unverified->slug, $content);
    }

    public function test_static_sitemap_and_unknown_type(): void
    {
        $this->get('/sitemaps/en/static.xml')->assertOk()->assertSee(url('/en/games'), false);
        $this->get('/sitemaps/en/secrets.xml')->assertNotFound();
        $this->get('/sitemaps/de/games.xml')->assertNotFound();
    }

    public function test_robots_txt(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('User-agent: *', $response->getContent());
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $response->getContent());
        // Non-production environments must never be indexed.
        $this->assertStringContainsString("Disallow: /\n", $response->getContent());
    }
}
