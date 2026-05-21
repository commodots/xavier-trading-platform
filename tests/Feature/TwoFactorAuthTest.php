<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_2fa_setup_returns_secret_and_qr(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/2fa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'secret', 'qr'])
            ->assertJsonPath('success', true);
    }

    public function test_2fa_confirm_with_valid_otp_enables_2fa(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create(['google2fa_secret' => $secret]);

        $otp = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)->postJson('/api/2fa/confirm', ['code' => $otp]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertTrue($user->fresh()->google2fa_enabled);
    }

    public function test_2fa_confirm_with_invalid_otp_fails(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create(['google2fa_secret' => $secret]);

        $response = $this->actingAs($user)->postJson('/api/2fa/confirm', ['code' => '000000']);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertFalse((bool) $user->fresh()->google2fa_enabled);
    }

    public function test_2fa_verify_endpoint_issues_token_on_valid_otp(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'google2fa_enabled' => true,
            'google2fa_secret'  => $secret,
        ]);

        $otp = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/2fa/verify', [
            'email' => $user->email,
            'token' => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('success', true);
    }

    public function test_2fa_verify_blocks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/2fa/verify', [
                'email' => $user->email,
                'token' => '000000',
            ]);
        }

        $response = $this->postJson('/api/2fa/verify', [
            'email' => $user->email,
            'token' => '000000',
        ]);

        $response->assertStatus(429);
    }

    public function test_2fa_disable_clears_secret_and_flag(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'google2fa_enabled' => true,
            'google2fa_secret'  => $secret,
        ]);

        $response = $this->actingAs($user)->postJson('/api/2fa/disable');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertFalse((bool) $user->fresh()->google2fa_enabled);
        $this->assertNull($user->fresh()->google2fa_secret);
    }
}
