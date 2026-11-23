<?php

namespace Tests\Feature;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorService $service;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TwoFactorService();
        $this->google2fa = new Google2FA();
    }

    public function test_generate_secret_stores_unconfirmed_secret(): void
    {
        $user = User::factory()->create();

        $secret = $this->service->generateSecret($user);

        $this->assertNotEmpty($secret);
        $user->refresh();
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_secret_is_stored_encrypted_in_database(): void
    {
        $user = User::factory()->create();

        $secret = $this->service->generateSecret($user);

        // Roh aus der DB gelesen darf nicht dem Klartext-Secret entsprechen.
        $raw = \DB::connection()
            ->table('users')
            ->where('id', $user->id)
            ->value('two_factor_secret');

        $this->assertNotSame($secret, $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_confirm_activates_two_factor_with_valid_code(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);

        $code = $this->google2fa->getCurrentOtp($secret);

        $this->assertTrue($this->service->confirm($user, $code));
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_confirm_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->service->generateSecret($user);

        $this->assertFalse($this->service->confirm($user, '000000'));
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_verify_for_user_accepts_valid_and_rejects_invalid(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);
        $this->service->confirm($user, $this->google2fa->getCurrentOtp($secret));
        $user->refresh();

        $this->assertTrue($this->service->verifyForUser($user, $this->google2fa->getCurrentOtp($secret)));
        $this->assertFalse($this->service->verifyForUser($user, '123456'));
    }

    public function test_verify_code_strips_whitespace(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);

        $otp = $this->google2fa->getCurrentOtp($secret);
        $spaced = substr($otp, 0, 3) . ' ' . substr($otp, 3);

        $this->assertTrue($this->service->verifyCode($secret, $spaced));
    }

    public function test_disable_clears_secret_and_devices(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);
        $this->service->confirm($user, $this->google2fa->getCurrentOtp($secret));
        $this->service->rememberDevice($user);

        $this->service->disable($user);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertSame(0, $user->trustedDevices()->count());
    }

    public function test_remember_device_stores_hashed_token(): void
    {
        $user = User::factory()->create();

        $token = $this->service->rememberDevice($user, 'Test-Browser', '127.0.0.1');

        $device = TwoFactorTrustedDevice::where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        // In der DB liegt nur der Hash, nicht das Klartext-Token.
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertNotSame($token, $device->token_hash);
    }

    public function test_is_trusted_device_recognizes_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $this->service->rememberDevice($user);

        $this->assertTrue($this->service->isTrustedDevice($user, $token));
        $this->assertFalse($this->service->isTrustedDevice($user, 'falsches-token'));
        $this->assertFalse($this->service->isTrustedDevice($user, null));
    }

    public function test_is_trusted_device_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $this->service->rememberDevice($user);

        TwoFactorTrustedDevice::where('user_id', $user->id)
            ->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($this->service->isTrustedDevice($user, $token));
    }

    public function test_is_trusted_device_isolated_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $token = $this->service->rememberDevice($userA);

        // Das Token von A darf für B nicht gelten.
        $this->assertFalse($this->service->isTrustedDevice($userB, $token));
    }

    public function test_forget_all_devices_removes_only_target_user_devices(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->service->rememberDevice($userA);
        $this->service->rememberDevice($userB);

        $this->service->forgetAllDevices($userA);

        $this->assertSame(0, $userA->trustedDevices()->count());
        $this->assertSame(1, $userB->trustedDevices()->count());
    }
}
