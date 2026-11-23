<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA();
    }

    /**
     * Legt einen Nutzer mit vollständig eingerichteter 2FA an.
     *
     * @return array{0: User, 1: string} Nutzer und Klartext-Secret
     */
    private function userWithTwoFactor(): array
    {
        $service = app(TwoFactorService::class);
        $user = User::factory()->create();
        $secret = $service->generateSecret($user);
        $service->confirm($user, $this->google2fa->getCurrentOtp($secret));
        $user->refresh();

        return [$user, $secret];
    }

    public function test_login_without_two_factor_logs_in_directly(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_with_two_factor_redirects_to_challenge(): void
    {
        [$user] = $this->userWithTwoFactor();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Nicht voll eingeloggt – nur Zwischenzustand.
        $this->assertGuest();
        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertEquals($user->id, session('login.id'));
    }

    public function test_challenge_page_renders_with_pending_login(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->get(route('two-factor.challenge'))->assertStatus(200);
    }

    public function test_challenge_redirects_to_login_without_pending_state(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_valid_code_completes_login(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'code' => $this->google2fa->getCurrentOtp($secret),
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_invalid_code_keeps_user_out(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'code' => '000000',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_remember_device_sets_trust_cookie_and_record(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'code' => $this->google2fa->getCurrentOtp($secret),
            'remember_device' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertCookie(TwoFactorChallengeController::TRUSTED_COOKIE);
        $this->assertSame(1, $user->trustedDevices()->count());
    }

    public function test_trusted_device_bypasses_challenge(): void
    {
        [$user] = $this->userWithTwoFactor();
        $token = app(TwoFactorService::class)->rememberDevice($user);

        $response = $this->withCookie(TwoFactorChallengeController::TRUSTED_COOKIE, $token)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        // Vertrauenswürdiges Gerät → direkter Login ohne Challenge.
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_can_view_setup_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertStatus(200)
            ->assertViewIs('auth.two-factor-setup');
    }

    public function test_user_can_confirm_and_enable_two_factor(): void
    {
        $user = User::factory()->create();
        // show() erzeugt das Secret.
        $this->actingAs($user)->get(route('two-factor.show'));
        $user->refresh();

        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $response = $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $code]);

        $response->assertSessionHas('status', 'two-factor-enabled');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_user_can_disable_two_factor(): void
    {
        [$user] = $this->userWithTwoFactor();

        $response = $this->actingAs($user)->delete(route('two-factor.disable'));

        $response->assertSessionHas('status', 'two-factor-disabled');
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_user_can_clear_trusted_devices(): void
    {
        [$user] = $this->userWithTwoFactor();
        app(TwoFactorService::class)->rememberDevice($user);

        $response = $this->actingAs($user)->delete(route('two-factor.devices.clear'));

        $response->assertSessionHas('status', 'two-factor-devices-cleared');
        $this->assertSame(0, $user->trustedDevices()->count());
    }

    public function test_admin_can_reset_user_two_factor(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$user] = $this->userWithTwoFactor();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.two-factor.reset', $user));

        $response->assertRedirect(route('admin.users.edit', $user));
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_admin_can_reset_user_trusted_devices(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$user] = $this->userWithTwoFactor();
        app(TwoFactorService::class)->rememberDevice($user);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.two-factor.devices.reset', $user));

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertSame(0, $user->trustedDevices()->count());
        // 2FA selbst bleibt aktiv.
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_planner_cannot_reset_other_users_two_factor(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        [$user] = $this->userWithTwoFactor();

        $this->actingAs($planner)
            ->post(route('admin.users.two-factor.reset', $user))
            ->assertForbidden();
    }
}
