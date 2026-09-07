<?php

namespace Tests\Feature;

use App\Jobs\SubmitFixedIncomeInvestment;
use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use App\Models\FixedIncomeTransaction;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FixedIncome\FixedIncomeMaturityService;
use App\Services\FixedIncome\FixedIncomeReinvestmentService;
use App\Services\FixedIncome\FixedIncomeReturnCalculator;
use App\Services\FixedIncomeInvestmentService;
use App\Services\FixedIncomeLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
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

        $this->assertTrue(
            collect(app('router')->getRoutes()->getRoutes())
                ->contains(fn ($route) => $route->uri() === 'api/fixed-income/investments/{fixedIncomeInvestment}/reinvest')
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

    public function test_duplicate_idempotency_key_returns_existing_investment_without_reserving_again(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 500000,
            'balance' => 500000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Idempotent Bond',
            'code' => 'GB-IDEMPOTENT',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
            'interest_rate' => 10,
            'tenor_days' => 30,
            'execution_mode' => 'manual',
        ]);

        $service = app(FixedIncomeInvestmentService::class);
        $first = $service->createFromWallet($user, $product, 200000, 'request-123');
        $second = $service->createFromWallet($user, $product, 200000, 'request-123');
        $wallet->refresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FixedIncomeInvestment::query()
            ->where('idempotency_key', 'request-123')
            ->count());
        $this->assertSame(300000.0, (float) $wallet->ngn_cleared);
        $this->assertSame(200000.0, (float) $wallet->locked);
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

    public function test_authoritative_calculator_and_maturity_are_idempotent(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 500000,
            'balance' => 500000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Maturity Bond',
            'code' => 'GB-MATURITY',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
            'interest_rate' => 15.5,
            'tenor_days' => 181,
            'calculation_method' => 'simple_interest',
            'day_count_basis' => 'actual_365',
            'execution_mode' => 'manual',
        ]);

        $calculation = app(FixedIncomeReturnCalculator::class)->calculate(
            $product,
            500000,
            now(),
            now()->addDays(181)
        );

        $this->assertSame(181, $calculation['days']);
        $this->assertSame(38431.51, $calculation['interest']);
        $this->assertSame(538431.51, $calculation['maturity_amount']);

        $investment = app(FixedIncomeInvestmentService::class)
            ->createFromWallet($user, $product, 500000);

        app(FixedIncomeLifecycleService::class)->activate($investment);
        $investment->update([
            'execution_date' => now()->subDay(),
            'maturity_date' => now(),
        ]);

        $maturityService = app(FixedIncomeMaturityService::class);
        $matured = $maturityService->mature($investment);
        $wallet->refresh();

        $this->assertSame('redeemed', $matured->status);
        $this->assertNotNull($matured->actual_interest);
        $this->assertNotNull($matured->actual_maturity_amount);
        $this->assertNotNull($matured->redeemed_at);
        $this->assertSame(500212.33, (float) $wallet->ngn_cleared);
        $this->assertSame(0.0, (float) $wallet->locked);

        $maturityService->mature($investment);

        $this->assertSame(1, FixedIncomeTransaction::query()
            ->where('fixed_income_investment_id', $investment->id)
            ->where('type', 'investment_redeemed')
            ->count());
        $this->assertSame(1, NewTransaction::query()
            ->where('type', 'fixed_income_redemption')
            ->where('user_id', $user->id)
            ->count());
        $this->assertSame(1, Ledger::query()
            ->where('type', 'FIXED_INCOME_REDEMPTION')
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_maturity_cannot_be_processed_before_due_date(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 500000,
            'balance' => 500000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Future Maturity Bond',
            'code' => 'GB-FUTURE-MATURITY',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
            'interest_rate' => 10,
            'tenor_days' => 30,
            'execution_mode' => 'manual',
        ]);
        $investment = app(FixedIncomeInvestmentService::class)
            ->createFromWallet($user, $product, 200000);
        app(FixedIncomeLifecycleService::class)->activate($investment);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Investment cannot be matured before its maturity date.'
        );

        app(FixedIncomeMaturityService::class)->mature($investment);
    }

    public function test_reinvestment_preserves_history_and_creates_a_new_reserved_investment(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 1100000,
            'balance' => 1100000,
        ]);
        $product = FixedIncomeProduct::create([
            'name' => 'Reinvestment Bond',
            'code' => 'GB-REINVEST',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 100000,
            'interest_rate' => 10,
            'tenor_days' => 30,
            'allow_reinvestment' => true,
            'execution_mode' => 'manual',
        ]);
        $investment = app(FixedIncomeInvestmentService::class)
            ->createFromWallet($user, $product, 500000);
        $investment->update([
            'status' => 'redeemed',
            'actual_maturity_amount' => 510000,
            'reinvestment_enabled' => true,
        ]);

        $newInvestment = app(FixedIncomeReinvestmentService::class)
            ->reinvest($investment);

        $this->assertSame('redeemed', $investment->refresh()->status);
        $this->assertNotSame($investment->reference, $newInvestment->reference);
        $this->assertSame(510000.0, (float) $newInvestment->principal_amount);
        $this->assertSame(510000.0, (float) $newInvestment->reserved_amount);
        $this->assertSame($newInvestment->id, $investment->refresh()->metadata['reinvested_into']);
        $this->assertSame($investment->id, $newInvestment->metadata['reinvestment_from']);
    }
}
