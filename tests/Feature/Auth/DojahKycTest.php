<?php

namespace Tests\Feature\Auth;

use App\Models\KycVerification;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DojahService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DojahKycTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'verification_level' => 0,
        ], $attrs));

        Wallet::factory()->create(['user_id' => $user->id, 'currency' => 'NGN']);
        Wallet::factory()->create(['user_id' => $user->id, 'currency' => 'USD']);

        return $user;
    }

    private function mockDojah(string $method, array $response): void
    {
        $mock = Mockery::mock(DojahService::class);
        $mock->shouldReceive($method)->andReturn($response);
        $mock->shouldReceive('storeResult')->andReturnUsing(function ($userId, $type, $result) {
            return KycVerification::updateOrCreate(
                ['user_id' => $userId, 'verification_type' => $type],
                ['status' => ($result['success'] ?? false) ? 'approved' : 'failed', 'response_json' => $result]
            );
        });
        $this->app->instance(DojahService::class, $mock);
    }

    // -------------------------------------------------------------------------
    // BVN
    // -------------------------------------------------------------------------

    public function test_bvn_verification_success(): void
    {
        $this->mockDojah('verifyBvn', ['success' => true, 'entity' => ['bvn' => '12345678901']]);

        $user = $this->makeUser();
        $res = $this->actingAs($user)->postJson('/api/kyc/bvn', ['bvn' => '12345678901']);

        $res->assertOk()->assertJsonFragment(['message' => 'BVN verified successfully.']);
    }

    public function test_bvn_verification_failure_returns_422(): void
    {
        $this->mockDojah('verifyBvn', ['success' => false, 'message' => 'BVN not found.']);

        $user = $this->makeUser();
        $res = $this->actingAs($user)->postJson('/api/kyc/bvn', ['bvn' => '00000000000']);

        $res->assertStatus(422)->assertJsonFragment(['message' => 'BVN not found.']);
    }

    public function test_bvn_requires_exactly_11_digits(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->postJson('/api/kyc/bvn', ['bvn' => '123'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // NIN
    // -------------------------------------------------------------------------

    public function test_nin_verification_success(): void
    {
        $this->mockDojah('verifyNin', ['success' => true, 'entity' => ['nin' => '12345678901']]);

        $user = $this->makeUser();
        $res = $this->actingAs($user)->postJson('/api/kyc/nin', ['nin' => '12345678901']);

        $res->assertOk()->assertJsonFragment(['message' => 'NIN verified successfully.']);
    }

    public function test_nin_verification_failure_returns_422(): void
    {
        $this->mockDojah('verifyNin', ['success' => false, 'message' => 'NIN not found.']);

        $user = $this->makeUser();
        $res = $this->actingAs($user)->postJson('/api/kyc/nin', ['nin' => '00000000000']);

        $res->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Verification level upgrades
    // -------------------------------------------------------------------------

    public function test_verification_level_reaches_2_when_both_bvn_and_nin_approved(): void
    {
        $user = $this->makeUser();

        // Create KYC profile with tier 0 initially
        $kycProfile = $user->kyc()->create([
            'tier' => 0,
            'status' => 'pending',
        ]);

        KycVerification::create([
            'user_id' => $user->id,
            'verification_type' => 'bvn',
            'status' => 'approved',
        ]);

        $this->mockDojah('verifyNin', ['success' => true, 'entity' => []]);

        $this->actingAs($user)->postJson('/api/kyc/nin', ['nin' => '12345678901']);

        // Refresh both user and kyc profile to get latest data
        $user->refresh();
        $kycProfile->refresh();
        
        // The verification_level accessor reads from kyc.tier
        $this->assertEquals(2, $kycProfile->tier);
    }

    // -------------------------------------------------------------------------
    // Selfie / liveness
    // -------------------------------------------------------------------------

    public function test_selfie_verification_success_sets_level_3(): void
    {
        $mock = Mockery::mock(DojahService::class);
        $mock->shouldReceive('checkLiveness')->andReturn([
            'success' => true,
            'entity' => ['confidence' => 95],
        ]);
        $mock->shouldReceive('extractSelfieImage')->andReturn('fakeimagebytes');
        $mock->shouldReceive('storeResult')->andReturn(new KycVerification);
        $this->app->instance(DojahService::class, $mock);

        $user = $this->makeUser();
        // Create KYC profile with tier 2
        $user->kyc()->create([
            'tier' => 2,
            'status' => 'pending',
        ]);
        
        $res = $this->actingAs($user)->postJson('/api/kyc/selfie', ['image' => base64_encode('fakeimagebytes')]);

        $res->assertOk()->assertJsonFragment(['verification_level' => 3]);
        $this->assertEquals(3, $user->fresh()->verification_level);
    }

    public function test_selfie_below_confidence_threshold_returns_422(): void
    {
        $mock = Mockery::mock(DojahService::class);
        $mock->shouldReceive('checkLiveness')->andReturn([
            'success' => true,
            'entity' => ['confidence' => 40],
        ]);
        $mock->shouldReceive('extractSelfieImage')->andReturn('fakeimagebytes');
        $mock->shouldReceive('storeResult')->andReturn(new KycVerification);
        $this->app->instance(DojahService::class, $mock);

        $user = $this->makeUser();
        // Create KYC profile with tier 2
        $user->kyc()->create([
            'tier' => 2,
            'status' => 'pending',
        ]);
        
        $this->actingAs($user)->postJson('/api/kyc/selfie', ['image' => base64_encode('fakeimagebytes')])
            ->assertStatus(422);
    }

    public function test_verify_liveness_route_accepts_the_legacy_endpoint(): void
    {
        $mock = Mockery::mock(DojahService::class);
        $mock->shouldReceive('checkLiveness')->andReturn([
            'success' => true,
            'entity' => ['confidence' => 95, 'image' => base64_encode('fakeimagebytes')],
        ]);
        $mock->shouldReceive('extractSelfieImage')->andReturn('fakeimagebytes');
        $mock->shouldReceive('storeResult')->andReturn(new KycVerification);
        $this->app->instance(DojahService::class, $mock);

        $user = $this->makeUser();
        // Create KYC profile with tier 2
        $user->kyc()->create([
            'tier' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson('/api/kyc/verify-liveness', ['image' => base64_encode('fakeimagebytes')])
            ->assertOk()
            ->assertJsonFragment(['verification_level' => 3]);
    }

    // -------------------------------------------------------------------------
    // KYC Status endpoint
    // -------------------------------------------------------------------------

    public function test_kyc_status_returns_correct_progress(): void
    {
        $user = $this->makeUser(['verification_level' => 2]);

        KycVerification::create(['user_id' => $user->id, 'verification_type' => 'bvn', 'status' => 'approved']);
        KycVerification::create(['user_id' => $user->id, 'verification_type' => 'nin', 'status' => 'approved']);

        $res = $this->actingAs($user)->getJson('/api/kyc/status');

        $res->assertOk()
            ->assertJsonFragment(['progress' => 75])
            ->assertJsonPath('steps.bvn', true)
            ->assertJsonPath('steps.nin', true)
            ->assertJsonPath('steps.selfie', false);
    }

    // -------------------------------------------------------------------------
    // KYC Level Middleware
    // -------------------------------------------------------------------------

    public function test_trading_blocked_for_verification_level_below_2(): void
    {
        $user = $this->makeUser();
        // Create KYC profile with tier 1 (below required level 2)
        $user->kyc()->create([
            'tier' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson('/api/orders', [
            'market' => 'crypto',
            'symbol' => 'BTC',
            'company' => 'Bitcoin',
            'market_price' => 50000,
            'amount' => 1000,
            'side' => 'buy',
        ])
            ->assertStatus(403)
            ->assertJsonFragment(['required_level' => 2]);
    }

    public function test_trading_allowed_for_verification_level_2(): void
    {
        // Just check the middleware passes — OmsController may return other errors
        $user = $this->makeUser();
        // Create KYC profile with tier 2
        $user->kyc()->create([
            'tier' => 2,
            'status' => 'pending',
        ]);

        $res = $this->actingAs($user)->postJson('/api/orders', []);

        // Should NOT be a 403 KYC block
        $this->assertNotEquals(403, $res->status());
    }

    public function test_withdrawal_blocked_for_level_below_3(): void
    {
        $user = $this->makeUser();
        // Enable 2FA
        $user->google2fa_enabled = true;
        $user->google2fa_secret = encrypt('JBSWY3DPEHPK3PXP');
        $user->save();
        
        // Create KYC profile with tier 2 (below required level 3)
        $user->kyc()->create([
            'tier' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson('/api/security/withdrawals', [
            'amount' => 1000,
            'currency' => 'NGN',
            'account_number' => '0123456789',
            'account_name' => 'Test User',
            'otp' => '123456',
        ])->assertStatus(403)->assertJsonFragment(['required_level' => 3]);
    }

    // -------------------------------------------------------------------------
    // 2FA enforcement on withdrawal
    // -------------------------------------------------------------------------

    public function test_withdrawal_blocked_without_2fa(): void
    {
        $user = $this->makeUser();
        // Create KYC profile with tier 3
        $user->kyc()->create([
            'tier' => 3,
            'status' => 'pending',
        ]);
        
        // Ensure 2FA is disabled
        $user->google2fa_enabled = false;
        $user->google2fa_secret = null;
        $user->save();

        $this->actingAs($user)->postJson('/api/security/withdrawals', [
            'amount' => 1000,
            'currency' => 'NGN',
            'account_number' => '0123456789',
            'account_name' => 'Test User',
        ])->assertStatus(422)->assertJsonPath('errors.2fa.0', 'You must enable Two-Factor Authentication before withdrawing.');
    }

    // -------------------------------------------------------------------------
    // Unauthenticated access
    // -------------------------------------------------------------------------

    public function test_kyc_endpoints_require_authentication(): void
    {
        $this->postJson('/api/kyc/bvn', ['bvn' => '12345678901'])->assertStatus(401);
        $this->postJson('/api/kyc/nin', ['nin' => '12345678901'])->assertStatus(401);
        $this->postJson('/api/kyc/selfie', ['image' => 'abc'])->assertStatus(401);
        $this->getJson('/api/kyc/status')->assertStatus(401);
    }
}
