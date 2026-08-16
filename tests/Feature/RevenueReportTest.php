<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\PlatformEarning;
use App\Models\RevenueRecord;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Reports\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected function makeUser(string $name = 'John Doe'): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    protected function makePlan(float $price = 25000): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Premium',
            'price' => $price,
            'duration_days' => 30,
            'features' => json_encode(['premium']),
            'tier' => 'premium',
        ]);
    }

    protected function makeSubscription(User $user, SubscriptionPlan $plan, string $status = 'active'): UserSubscription
    {
        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'expires_at' => now()->addDays($plan->duration_days),
            'status' => $status,
        ]);
    }

    protected function makePlatformEarning(User $user, float $amount, float $amountNgn, string $source = 'withdrawal_charge', string $status = 'completed'): PlatformEarning
    {
        $transaction = \App\Models\NewTransaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $amount,
            'currency' => 'USD',
            'charge' => 0,
            'net_amount' => $amount,
            'status' => $status,
        ]);

        return PlatformEarning::create([
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'currency' => 'USD',
            'amount_ngn' => $amountNgn,
            'source' => $source,
        ]);
    }

    protected function makeFee(User $user, float $amount, string $type = 'trade_fee'): Fee
    {
        return Fee::create([
            'user_id' => $user->id,
            'trade_id' => null,
            'amount' => $amount,
            'type' => $type,
        ]);
    }

    protected function generate(array $params = []): array
    {
        $request = Request::create('/admin/reports/revenue', 'GET', $params);

        return app(RevenueReportService::class)->generate($request);
    }

    /**
     * The revenue tables the service relies on exist.
     */
    public function test_revenue_source_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('user_subscriptions'));
        $this->assertTrue(Schema::hasTable('subscription_plans'));
        $this->assertTrue(Schema::hasTable('platform_earnings'));
        $this->assertTrue(Schema::hasTable('fees'));
        $this->assertTrue(Schema::hasTable('revenue_records'));
    }

    /**
     * Test 1 — All revenue: no filters.
     */
    public function test_all_revenue_without_filters(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        $this->makeSubscription($user, $plan);
        $this->makePlatformEarning($user, 100, 160000);

        $result = $this->generate();

        $expected = 25000 + 160000;
        $this->assertEquals($expected, $result['summary']['total_revenue']);
        $this->assertEquals(2, $result['summary']['transaction_count']);
    }

    /**
     * Test 2 — Current month filter.
     */
    public function test_current_month_filter(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        // This subscription is in the current month
        $this->makeSubscription($user, $plan);

        // An old subscription (previous month context simulated via created_at)
        $old = $this->makeSubscription($user, $plan);
        $old->created_at = now()->subMonths(2);
        $old->save();

        $result = $this->generate([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

        $this->assertEquals(25000, $result['summary']['total_revenue']);
        $this->assertEquals(1, $result['summary']['transaction_count']);
    }

    /**
     * Test 3 — Subscription only.
     */
    public function test_subscription_only_filter(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(30000);
        $this->makeSubscription($user, $plan);
        $this->makePlatformEarning($user, 100, 160000);

        $result = $this->generate(['source' => 'subscription']);

        $this->assertEquals(30000, $result['summary']['total_revenue']);
        $this->assertEquals(1, $result['summary']['transaction_count']);
        $this->assertEquals('subscription', $result['table']['data'][0]['source']);
    }

    /**
     * Test 4 — Search SUB- only returns subscription references.
     */
    public function test_search_sub_reference(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(30000);
        $this->makeSubscription($user, $plan);
        $this->makePlatformEarning($user, 100, 160000);

        $result = $this->generate(['search' => 'SUB-']);

        $this->assertEquals(30000, $result['summary']['total_revenue']);
        foreach ($result['table']['data'] as $row) {
            $this->assertStringContainsString('SUB-', $row['reference']);
        }
    }

    /**
     * Test 5 — Failed payment must not increase revenue.
     */
    public function test_failed_payment_is_excluded(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        $this->makeSubscription($user, $plan, 'cancelled');
        $this->makePlatformEarning($user, 100, 160000, 'withdrawal_charge', 'failed');

        $result = $this->generate();

        $this->assertEquals(0, $result['summary']['total_revenue']);
        $this->assertEquals(0, $result['summary']['transaction_count']);
    }

    /**
     * Test 6 — Cancelled payment must not increase revenue.
     */
    public function test_cancelled_payment_is_excluded(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        $this->makeSubscription($user, $plan, 'cancelled');
        $this->makePlatformEarning($user, 100, 160000, 'withdrawal_charge', 'cancelled');

        $result = $this->generate();

        $this->assertEquals(0, $result['summary']['total_revenue']);
        $this->assertEquals(0, $result['summary']['transaction_count']);
    }

    /**
     * Reconciliation — independent SUM per source must equal report total.
     */
    public function test_report_reconciles_with_source_records(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        $this->makeSubscription($user, $plan);
        $this->makeSubscription($user, $plan, 'cancelled'); // excluded
        $this->makePlatformEarning($user, 100, 160000);
        $this->makeFee($user, 5000);

        $result = $this->generate();

        // Independent database sums
        $subscriptionTotal = (float) UserSubscription::query()
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->whereIn('user_subscriptions.status', ['paid', 'completed', 'successful', 'active', 'approved'])
            ->sum('subscription_plans.price');

        $platformTotal = (float) PlatformEarning::query()
            ->leftJoin('new_transactions_table as t', 'platform_earnings.transaction_id', '=', 't.id')
            ->whereIn(DB::raw('COALESCE(NULLIF(t.status, ""), "paid")'), ['paid', 'completed', 'successful', 'active', 'approved'])
            ->sum(DB::raw('COALESCE(NULLIF(platform_earnings.amount_ngn, 0), platform_earnings.amount, 0)'));

        $feeTotal = (float) Fee::query()->sum('amount');

        $reconciled = $subscriptionTotal + $platformTotal + $feeTotal;

        $this->assertEquals($reconciled, $result['summary']['total_revenue']);
    }

    /**
     * Export total matches screen total.
     */
    public function test_export_data_matches_screen_total(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(25000);
        $this->makeSubscription($user, $plan);
        $this->makePlatformEarning($user, 100, 160000);

        // Same request params used for both the screen and the export
        $params = ['per_page' => 10000];
        $result = $this->generate($params);

        $screenTotal = $result['summary']['total_revenue'];
        $exportRows = collect($result['table']['data'])->sum('amount');

        $this->assertEquals($screenTotal, $exportRows);
    }
}