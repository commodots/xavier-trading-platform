<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\SystemSetting;
use App\Models\TransactionCharge;
use App\Models\NewTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalLimit;
use App\Services\FxEngine;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // FX Engine
    // -------------------------------------------------------------------------

    public function test_fx_engine_applies_markup_to_base_rate(): void
    {
        \App\Models\FxConfig::create([
            'target_margin_percent' => 2,
            'min_markup'            => 1,
            'max_markup'            => 5,
            'volatility_threshold'  => 10,
        ]);

        FxRate::create(['from_currency' => 'USD', 'to_currency' => 'NGN', 'base_rate' => 1500, 'effective_rate' => 1530]);

        $result = app(FxEngine::class)->calculateEffectiveRate(1500);

        $this->assertArrayHasKey('effective_rate', $result);
        $this->assertArrayHasKey('markup_used', $result);
        $this->assertGreaterThan(1500, $result['effective_rate']);
        $this->assertGreaterThanOrEqual(1, $result['markup_used']);
        $this->assertLessThanOrEqual(5, $result['markup_used']);
    }

    public function test_fx_engine_rejects_zero_base_rate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(FxEngine::class)->calculateEffectiveRate(0);
    }

    public function test_fx_engine_rejects_negative_base_rate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(FxEngine::class)->calculateEffectiveRate(-100);
    }

    public function test_fx_engine_clamps_markup_to_max(): void
    {
        \App\Models\FxConfig::create([
            'target_margin_percent' => 5,
            'min_markup'            => 1,
            'max_markup'            => 5,
            'volatility_threshold'  => 0, // Always triggers +1 volatility
        ]);

        // Seed 5 rates with high spread to trigger volatility
        foreach ([1000, 1200, 1400, 1600, 1800] as $rate) {
            FxRate::create(['from_currency' => 'USD', 'to_currency' => 'NGN', 'base_rate' => $rate, 'effective_rate' => $rate]);
        }

        $result = app(FxEngine::class)->calculateEffectiveRate(1500);

        // Markup must never exceed max_markup of 5
        $this->assertLessThanOrEqual(5, $result['markup_used']);
    }

    public function test_fx_conversion_preview_divides_correctly(): void
    {
        $user = User::factory()->create();

        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency'   => 'USD',
            'base_rate'     => 1500,
            'effective_rate' => 1500,
        ]);

        $response = $this->actingAs($user)->getJson('/api/wallet/preview?amount=15000&from=NGN');

        $response->assertStatus(200);
        $response->assertJsonPath('converted', 10.0);
        $response->assertJsonPath('to_currency', 'USD');
    }

    public function test_fx_preview_returns_error_for_zero_rate(): void
    {
        $user = User::factory()->create();

        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency'   => 'USD',
            'base_rate'     => 0,
            'effective_rate' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/wallet/preview?amount=1000&from=NGN');

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Fee clamping
    // -------------------------------------------------------------------------

    public function test_percentage_fee_is_clamped_to_transaction_amount(): void
    {
        // 200% fee — should be clamped to the transaction amount itself
        TransactionCharge::create([
            'transaction_type' => 'deposit',
            'charge_type'      => 'percentage',
            'value'            => 200,
            'active'           => true,
        ]);

        $user = User::factory()->create();
        $tx   = NewTransaction::create([
            'user_id'  => $user->id,
            'type'     => 'deposit',
            'amount'   => 1000,
            'currency' => 'NGN',
            'status'   => 'pending',
        ]);

        TransactionService::applyFees($tx);
        $tx->refresh();

        // Fee must never exceed the transaction amount
        $this->assertLessThanOrEqual($tx->amount, $tx->charge);
        $this->assertEquals(1000, $tx->charge);
        // net_amount on deposit = amount - fee = 0
        $this->assertEquals(0, $tx->net_amount);
    }

    public function test_zero_fee_does_not_create_platform_earning(): void
    {
        // No charge config = 0 fee
        $user = User::factory()->create();
        $tx   = NewTransaction::create([
            'user_id'  => $user->id,
            'type'     => 'deposit',
            'amount'   => 5000,
            'currency' => 'NGN',
            'status'   => 'pending',
        ]);

        TransactionService::applyFees($tx);

        $this->assertDatabaseMissing('platform_earnings', ['transaction_id' => $tx->id]);
    }

    public function test_flat_fee_creates_platform_earning(): void
    {
        TransactionCharge::create([
            'transaction_type' => 'withdrawal',
            'charge_type'      => 'flat',
            'value'            => 500,
            'active'           => true,
        ]);

        $user = User::factory()->create();
        $tx   = NewTransaction::create([
            'user_id'  => $user->id,
            'type'     => 'withdrawal',
            'amount'   => 10000,
            'currency' => 'NGN',
            'status'   => 'pending',
        ]);

        TransactionService::applyFees($tx);

        $this->assertDatabaseHas('platform_earnings', [
            'transaction_id' => $tx->id,
            'amount'         => 500,
        ]);
    }

    // -------------------------------------------------------------------------
    // Withdrawal limit reset
    // -------------------------------------------------------------------------

    public function test_withdrawal_limit_resets_when_last_reset_was_yesterday(): void
    {
        $user  = User::factory()->create();
        $limit = WithdrawalLimit::create([
            'user_id'             => $user->id,
            'daily_limit_ngn'     => 500000,
            'daily_limit_usd'     => 2500,
            'daily_withdrawn_ngn' => 400000,
            'daily_withdrawn_usd' => 0,
            'last_reset_at'       => now()->subDay(), // yesterday
        ]);

        // canWithdraw should reset the counter and then allow
        $result = $limit->canWithdraw('NGN', 100000);

        $limit->refresh();
        $this->assertTrue($result);
        $this->assertEquals(0, $limit->daily_withdrawn_ngn);
    }

    public function test_withdrawal_limit_resets_when_last_reset_is_two_days_old(): void
    {
        $user  = User::factory()->create();
        $limit = WithdrawalLimit::create([
            'user_id'             => $user->id,
            'daily_limit_ngn'     => 500000,
            'daily_limit_usd'     => 2500,
            'daily_withdrawn_ngn' => 499000,
            'daily_withdrawn_usd' => 0,
            'last_reset_at'       => now()->subDays(3),
        ]);

        $result = $limit->canWithdraw('NGN', 300000);

        $limit->refresh();
        $this->assertTrue($result);
        $this->assertEquals(0, $limit->daily_withdrawn_ngn);
    }

    public function test_withdrawal_blocked_when_daily_limit_would_be_exceeded(): void
    {
        $user  = User::factory()->create();
        $limit = WithdrawalLimit::create([
            'user_id'             => $user->id,
            'daily_limit_ngn'     => 500000,
            'daily_limit_usd'     => 2500,
            'daily_withdrawn_ngn' => 450000,
            'daily_withdrawn_usd' => 0,
            'last_reset_at'       => now(), // reset today already
        ]);

        $result = $limit->canWithdraw('NGN', 100000); // 450k + 100k = 550k > 500k

        $this->assertFalse($result);
    }

    public function test_withdrawal_blocked_during_cooldown(): void
    {
        $user  = User::factory()->create();
        $limit = WithdrawalLimit::create([
            'user_id'         => $user->id,
            'daily_limit_ngn' => 500000,
            'daily_limit_usd' => 2500,
            'last_reset_at'   => now(),
            'cooldown_until'  => now()->addHours(12),
        ]);

        $result = $limit->canWithdraw('NGN', 1000);

        $this->assertFalse($result);
    }

    public function test_withdrawal_allowed_after_cooldown_expires(): void
    {
        $user  = User::factory()->create();
        $limit = WithdrawalLimit::create([
            'user_id'             => $user->id,
            'daily_limit_ngn'     => 500000,
            'daily_limit_usd'     => 2500,
            'daily_withdrawn_ngn' => 0,
            'last_reset_at'       => now(),
            'cooldown_until'      => now()->subHour(), // expired
        ]);

        $result = $limit->canWithdraw('NGN', 10000);

        $this->assertTrue($result);
    }
}
