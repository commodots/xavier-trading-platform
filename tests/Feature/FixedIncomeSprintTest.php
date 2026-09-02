<?php

namespace Tests\Feature;

use App\Jobs\SubmitFixedIncomeInvestment;
use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FixedIncomeInvestmentService;
use App\Services\FixedIncomeLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class FixedIncomeSprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_route_is_registered_without_duplicate_prefix(): void
    {
        $this->assertTrue(
            collect(app('router')->getRoutes()->getRoutes())
                ->contains(fn ($route) => $route->uri() === 'api/fixed-income/products/{fixedIncomeProduct}/invest')
        );
    }

    public function test_wallet_reservation_and_release_keep_balances_consistent(): void
    {
        $wallet = Wallet::factory()->create([
            'ngn_cleared' => 500000,
            'balance' => 500000,
        ]);

        $wallet->reserve(200000);
        $wallet->refresh();

        $this->assertSame(300000.0, (float) $wallet->ngn_cleared);
        $this->assertSame(200000.0, (float) $wallet->locked);
        $this->assertSame(500000.0, (float) $wallet->balance);

        $wallet->releaseReservation(200000);
        $wallet->refresh();

        $this->assertSame(500000.0, (float) $wallet->ngn_cleared);
        $this->assertSame(0.0, (float) $wallet->locked);
        $this->assertSame(500000.0, (float) $wallet->balance);
    }

    public function test_wallet_funding_creates_investment_and_dispatches_automated_execution_after_commit(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        Wallet::factory()->create([
            'user_id' => $user->id,
            'ngn_cleared' => 500000,
            'balance' => 500000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Government Bond',
            'code' => 'GB-TEST',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
            'interest_rate' => 10,
            'tenor_days' => 365,
            'execution_mode' => 'automated',
            'provider' => 'test-provider',
        ]);

        $investment = app(FixedIncomeInvestmentService::class)
            ->createFromWallet($user, $product, 500000);

        $this->assertSame('pending_execution', $investment->status);
        $this->assertSame('wallet', $investment->funding_method);
        $this->assertDatabaseHas('fixed_income_investments', [
            'id' => $investment->id,
            'expected_interest' => 50000,
        ]);
        Bus::assertDispatched(SubmitFixedIncomeInvestment::class);
    }

    public function test_rejecting_pending_investment_releases_reserved_funds(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'ngn_cleared' => 300000,
            'balance' => 500000,
            'locked' => 200000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Government Bond',
            'code' => 'GB-REJECT',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
        ]);
        $investment = FixedIncomeInvestment::create([
            'user_id' => $user->id,
            'fixed_income_product_id' => $product->id,
            'reference' => 'FI-REJECT-TEST',
            'principal_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'pending_execution',
            'funding_method' => 'wallet',
            'execution_mode' => 'manual',
        ]);

        app(FixedIncomeLifecycleService::class)->reject($investment, 'Unavailable');
        $wallet->refresh();

        $this->assertSame('rejected', $investment->refresh()->status);
        $this->assertSame(500000.0, (float) $wallet->ngn_cleared);
        $this->assertSame(0.0, (float) $wallet->locked);
        $this->assertDatabaseHas('fixed_income_transactions', [
            'fixed_income_investment_id' => $investment->id,
            'type' => 'investment_rejected',
        ]);

    }
}
