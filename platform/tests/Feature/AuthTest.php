<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'nickname' => 'new_player',
            'email' => 'new@example.com',
            'password' => 'Secret12345',
            'password_confirmation' => 'Secret12345',
            'terms' => '1',
        ], $overrides);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/en/register')->assertOk()->assertSee('name="nickname"', false)->assertSee('name="terms"', false);
    }

    public function test_user_can_register(): void
    {
        Notification::fake();

        $this->post('/en/register', $this->registration())
            ->assertRedirect('/en/account/verify-email');

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new_player', $user->nickname);
        $this->assertSame('en', $user->locale);
        $this->assertSame('player', $user->role->value);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_validates_fields(): void
    {
        User::factory()->create(['nickname' => 'taken', 'email' => 'taken@example.com']);

        $this->post('/en/register', $this->registration([
            'nickname' => 'taken', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'terms' => null,
        ]))->assertSessionHasErrors(['nickname', 'email', 'password', 'terms']);

        $this->post('/en/register', $this->registration(['nickname' => 'bad nick!']))->assertSessionHasErrors('nickname');
        $this->assertGuest();
    }

    public function test_registration_closed_returns_403(): void
    {
        Setting::put('site.registration_open', false);

        $this->get('/en/register')->assertForbidden();
        $this->post('/en/register', $this->registration())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->post('/en/login', ['email' => 'me@example.com', 'password' => 'password'])->assertRedirect('/en');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@example.com']);

        $this->post('/en/login', ['email' => 'me@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'me@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/en/login', ['email' => 'me@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/en/login', ['email' => 'me@example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['email' => 'bad@example.com']);

        $this->post('/en/login', ['email' => 'bad@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/en')->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();

        $this->get('/en/account')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_password_reset_request_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);

        $existing = $this->from('/en/forgot-password')->post('/en/forgot-password', ['email' => 'real@example.com']);
        $missing = $this->from('/en/forgot-password')->post('/en/forgot-password', ['email' => 'ghost@example.com']);

        foreach ([$existing, $missing] as $response) {
            $response->assertRedirect('/en/forgot-password')->assertSessionHasNoErrors();
        }
        $this->assertSame($existing->getSession()->get('status'), $missing->getSession()->get('status'));
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_with_token(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);
        $token = app('auth.password.broker')->createToken($user);

        $this->post('/en/reset-password', [
            'token' => $token, 'email' => 'real@example.com',
            'password' => 'BrandNew12345', 'password_confirmation' => 'BrandNew12345',
        ])->assertRedirect('/en/login');

        $this->post('/en/login', ['email' => 'real@example.com', 'password' => 'BrandNew12345']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_area_requires_login(): void
    {
        $this->get('/en/account')->assertRedirect('/en/login');
    }

    public function test_logged_in_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/en/login')->assertRedirect('/en');
    }
}
