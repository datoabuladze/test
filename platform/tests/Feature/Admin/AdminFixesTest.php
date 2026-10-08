<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Http\Controllers\Admin\TranslationController;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\User;
use App\Services\Ads;
use App\Services\Import\Adapters\GameDistributionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(TranslationController::overridesPath());
        parent::tearDown();
    }

    public function test_moderator_cannot_unsuspend_a_suspended_admin(): void
    {
        $admin = User::factory()->role(Role::Admin)->suspended()->create();
        $this->actingAs($this->userWithRole(Role::Moderator))
            ->post("/admin/users/{$admin->id}/unsuspend")->assertForbidden();
        $this->assertTrue($admin->refresh()->isSuspended());
    }

    public function test_translation_edits_are_stored_outside_the_lang_folder_and_applied(): void
    {
        $shipped = File::get(lang_path('ka.json'));
        $key = array_key_first(json_decode($shipped, true));

        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->post('/admin/translations', ['translations' => ['ka' => [TranslationController::encodeKey($key) => 'ტესტი']]])
            ->assertSessionHasNoErrors();

        $this->assertSame($shipped, File::get(lang_path('ka.json')), 'shipped file must not change');
        $this->assertSame('ტესტი', json_decode(File::get(TranslationController::overridesPath('ka.json')), true)[$key]);
        app('translator')->setLoaded([]);
        $this->assertSame('ტესტი', __($key, [], 'ka'));
    }

    public function test_game_distribution_rows_carry_a_source_url(): void
    {
        $row = (new GameDistributionAdapter)->map(['Md5' => 'abc', 'Title' => 'T', 'Url' => 'https://html5.gamedistribution.com/abc/']);
        $this->assertSame('https://html5.gamedistribution.com/abc/', $row['source_url']);
    }

    public function test_adsense_campaign_is_not_shown_or_counted_without_a_client_id(): void
    {
        config(['platform.ads.adsense_client' => null]);
        $placement = AdPlacement::query()->create(['key' => 'test_slot', 'name' => 'Test', 'is_enabled' => true]);
        AdCampaign::query()->create(['ad_placement_id' => $placement->id, 'name' => 'A', 'type' => 'adsense', 'is_active' => true, 'adsense_slot' => '1']);

        $this->assertNull(app(Ads::class)->forPlacement('test_slot'));
        $this->assertDatabaseCount('ad_stats_daily', 0);
    }

    public function test_click_on_an_ended_campaign_is_not_found(): void
    {
        $placement = AdPlacement::query()->create(['key' => 'test_slot', 'name' => 'Test', 'is_enabled' => true]);
        $c = AdCampaign::query()->create(['ad_placement_id' => $placement->id, 'name' => 'B', 'type' => 'image', 'is_active' => true,
            'target_url' => 'https://example.com', 'ends_at' => now()->subDay()]);

        $this->get(route('ads.click', $c))->assertNotFound();
    }
}
