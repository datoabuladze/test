<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SECTIONS = [
        '/admin', '/admin/games', '/admin/categories', '/admin/imports', '/admin/providers', '/admin/reports',
        '/admin/pages', '/admin/menus', '/admin/homepage', '/admin/design', '/admin/users', '/admin/ads',
        '/admin/seo', '/admin/settings', '/admin/translations', '/admin/analytics', '/admin/audit',
    ];

    /** Sections each role is expected to reach; everything else in SECTIONS must be 403. */
    public static function roleMatrix(): array
    {
        return [
            'super_admin' => [Role::SuperAdmin, self::SECTIONS],
            'admin' => [Role::Admin, self::SECTIONS],
            'game_manager' => [Role::GameManager, ['/admin', '/admin/games', '/admin/categories', '/admin/imports', '/admin/providers', '/admin/reports', '/admin/analytics']],
            'content_editor' => [Role::ContentEditor, ['/admin', '/admin/pages', '/admin/menus', '/admin/homepage', '/admin/seo', '/admin/categories']],
            'moderator' => [Role::Moderator, ['/admin', '/admin/users', '/admin/reports']],
            'analytics_viewer' => [Role::AnalyticsViewer, ['/admin', '/admin/analytics']],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/en/login');
        $this->get('/admin/games')->assertRedirect('/en/login');
        $this->post('/admin/games', [])->assertRedirect('/en/login');
    }

    public function test_player_gets_403_everywhere(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (self::SECTIONS as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_suspended_staff_lose_access(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->assertTrue($admin->hasPermission('games.manage'));
        $admin->forceFill(['suspended_at' => now()]);
        $this->assertFalse($admin->hasPermission('games.manage'));
        $this->assertFalse($admin->isStaff());
    }

    #[DataProvider('roleMatrix')]
    public function test_role_can_reach_only_its_sections(Role $role, array $allowed): void
    {
        $this->actingAs($this->userWithRole($role));

        foreach (self::SECTIONS as $path) {
            $status = $this->get($path)->status();
            if (in_array($path, $allowed, true)) {
                $this->assertSame(200, $status, "{$role->value} should reach $path");
            } else {
                $this->assertSame(403, $status, "{$role->value} should be forbidden from $path");
            }
        }
    }

    public function test_moderator_can_suspend_players_but_not_change_roles_or_touch_staff(): void
    {
        $mod = $this->userWithRole(Role::Moderator);
        $player = User::factory()->create();
        $editor = $this->userWithRole(Role::ContentEditor);
        $this->actingAs($mod);

        $this->post("/admin/users/{$player->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->assertSame(Role::Player, $player->fresh()->role);

        $this->post("/admin/users/{$player->id}/suspend", ['reason' => 'Spam'])->assertRedirect();
        $this->assertNotNull($player->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspend', 'subject_id' => $player->id, 'user_id' => $mod->id]);

        $this->post("/admin/users/{$editor->id}/suspend", ['reason' => 'x'])->assertForbidden();
        $this->assertNull($editor->fresh()->suspended_at);
    }

    public function test_admin_can_change_a_players_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $player = User::factory()->create();

        $this->actingAs($admin)->post("/admin/users/{$player->id}/role", ['role' => 'content_editor'])->assertRedirect();

        $this->assertSame(Role::ContentEditor, $player->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role', 'subject_id' => $player->id]);
    }

    public function test_users_cannot_change_their_own_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->actingAs($admin)->post("/admin/users/{$admin->id}/role", ['role' => 'super_admin'])->assertForbidden();
        $this->assertSame(Role::Admin, $admin->fresh()->role);

        $super = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($super)->post("/admin/users/{$super->id}/role", ['role' => 'player'])->assertForbidden();
        $this->assertSame(Role::SuperAdmin, $super->fresh()->role);
    }

    public function test_only_super_admin_can_grant_or_revoke_super_admin(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $super = $this->userWithRole(Role::SuperAdmin);
        $player = User::factory()->create();

        $this->actingAs($admin)->post("/admin/users/{$player->id}/role", ['role' => 'super_admin'])->assertForbidden();
        $this->assertSame(Role::Player, $player->fresh()->role);

        $this->actingAs($admin)->post("/admin/users/{$super->id}/role", ['role' => 'player'])->assertForbidden();
        $this->assertSame(Role::SuperAdmin, $super->fresh()->role);

        $this->actingAs($super)->post("/admin/users/{$player->id}/role", ['role' => 'super_admin'])->assertRedirect();
        $this->assertSame(Role::SuperAdmin, $player->fresh()->role);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $player = User::factory()->create();
        $this->actingAs($this->userWithRole(Role::Admin))
            ->post("/admin/users/{$player->id}/role", ['role' => 'overlord'])
            ->assertSessionHasErrors('role');
    }

    public function test_game_manager_can_publish_but_content_editor_cannot(): void
    {
        $game = Game::factory()->draft()->create();

        $this->actingAs($this->userWithRole(Role::ContentEditor))
            ->post("/admin/games/{$game->slug}/publish")->assertForbidden();
        $this->assertFalse($game->isPublic());

        $this->actingAs($this->userWithRole(Role::GameManager))
            ->post("/admin/games/{$game->slug}/publish")->assertRedirect()->assertSessionHas('status', 'Published.');
        $this->assertTrue($game->isPublic());
    }

    public function test_staff_with_games_manage_can_view_hidden_game_page(): void
    {
        $game = Game::factory()->draft()->create();
        $this->actingAs($this->userWithRole(Role::GameManager))->get('/en/game/'.$game->slug)
            ->assertOk()->assertSee('noindex', false);
        $this->actingAs($this->userWithRole(Role::AnalyticsViewer))->get('/en/game/'.$game->slug)->assertNotFound();
    }
}
