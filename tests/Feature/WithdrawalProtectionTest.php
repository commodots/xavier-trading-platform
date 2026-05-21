<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WithdrawalProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalProtectionTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawalProtectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WithdrawalProtectionService::class);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'subscription_status' => 'active',
            'wallet_debt'         => 0,
        ], $attrs));
    }

    private function makeWallet(User $user, float $cleared = 10000): Wallet
    {
        return Wallet::create([
            'user_id'       => $user->id,
            'currency'      => 'NGN',
            'ngn_cleared'   => $cleared,
            'ngn_uncleared' => 0,
            'balance'       => $cleared,
            'locked'        => 0,
        ]);
    }

    public function test_suspended_account_blocked(): void
    {
        $user = $this->makeUser(['subscription_status' => 'suspended']);
        $this->makeWallet($user, 10000);

        $result = $this->service->check($user, 1000);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('suspended', $result['message']);
    }

    public function test_inactive_account_blocked(): void
    {
        $user = $this->makeUser(['subscription_status' => 'inactive']);
        $this->makeWallet($user, 10000);

        $result = $this->service->check($user, 1000);

        $this->assertFalse($result['allowed']);
    }

    public function test_outstanding_debt_blocks_withdrawal(): void
    {
        $user = $this->makeUser(['wallet_debt' => 500]);
        $this->makeWallet($user, 10000);

        $result = $this->service->check($user, 1000);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('debt', $result['message']);
    }

    public function test_insufficient_cleared_balance_blocked(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 200);

        $result = $this->service->check($user, 1000);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('cleared', $result['message']);
    }

    public function test_valid_withdrawal_allowed(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 10000);

        $result = $this->service->check($user, 5000);

        $this->assertTrue($result['allowed']);
    }

    public function test_withdrawal_endpoint_blocked_for_suspended_user(): void
    {
        $user = $this->makeUser(['subscription_status' => 'suspended']);
        $this->makeWallet($user, 10000);

        $response = $this->actingAs($user)->postJson('/api/withdraw', [
            'amount'            => 1000,
            'currency'          => 'NGN',
            'linked_account_id' => 1,
            'withdrawal_otp'    => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }
}
