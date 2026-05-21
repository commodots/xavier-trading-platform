<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\BillingService;
use App\Models\BillingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'subscription_status' => 'active',
            'wallet_balance'      => 0,
            'wallet_debt'         => 0,
            'next_fee_due_at'     => now(),
        ], $attrs));
    }

    public function test_fee_deducted_when_balance_sufficient(): void
    {
        $user = $this->makeUser(['wallet_balance' => 5000]);

        app(BillingService::class)->chargePlatformFee($user);

        $user->refresh();
        $this->assertEquals(4000, $user->wallet_balance);
        $this->assertEquals(0, $user->wallet_debt);
        $this->assertEquals('active', $user->subscription_status);
        $this->assertDatabaseHas('billing_records', [
            'user_id' => $user->id,
            'status'  => 'paid',
            'amount'  => 1000,
        ]);
    }

    public function test_debt_created_when_balance_insufficient(): void
    {
        $user = $this->makeUser(['wallet_balance' => 400]);

        app(BillingService::class)->chargePlatformFee($user);

        $user->refresh();
        $this->assertEquals(0, $user->wallet_balance);
        $this->assertEquals(600, $user->wallet_debt);
        $this->assertDatabaseHas('billing_records', [
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);
    }

    public function test_account_suspended_when_debt_exceeds_threshold(): void
    {
        $user = $this->makeUser(['wallet_balance' => 0, 'wallet_debt' => 5000]);

        app(BillingService::class)->chargePlatformFee($user);

        $user->refresh();
        $this->assertEquals('suspended', $user->subscription_status);
        // Suspension notification queued to database channel
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $user->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_clear_debt_restores_active_status(): void
    {
        $user = $this->makeUser([
            'wallet_balance'      => 0,
            'wallet_debt'         => 2000,
            'subscription_status' => 'suspended',
        ]);

        app(BillingService::class)->clearDebt($user, 2000);

        $user->refresh();
        $this->assertEquals(0, $user->wallet_debt);
        $this->assertEquals('active', $user->subscription_status);
    }

    public function test_clear_debt_partial_topup_reduces_debt(): void
    {
        $user = $this->makeUser(['wallet_balance' => 0, 'wallet_debt' => 2000]);

        app(BillingService::class)->clearDebt($user, 500);

        $user->refresh();
        $this->assertEquals(1500, $user->wallet_debt);
        $this->assertEquals(0, $user->wallet_balance);
    }
}
