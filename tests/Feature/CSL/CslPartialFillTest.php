<?php

namespace Tests\Feature\CSL;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslOrderReconciliationService;
use App\Services\CSL\CslTradeXClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslPartialFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_fill_creates_only_the_newly_executed_quantity(): void
    {
        $user = User::factory()->create();

        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 10500,
            'ngn_cleared' => 0,
            'ngn_uncleared' => 0,
            'locked' => 10500,
            'status' => 'active',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'TIP',
            'side' => 'buy',
            'type' => 'limit',
            'price' => 105,
            'quantity' => 100,
            'amount' => 10500,
            'market_price' => 105,
            'filled_quantity' => 0,
            'status' => 'open',
            'provider' => 'csl',
            'provider_order_id' => '100001',
            'provider_market_account_id' => 'TEST-MA-001',
            'provider_submitted_at' => now(),
            'currency' => 'NGN',
        ]);

        // First reconciliation: CSL reports 40 of 100 executed.
        $this->reconcileWith($this->cslFixture('partial_fill'));

        $this->assertSame(40.0, (float) $order->fresh()->filled_quantity);
        $this->assertSame('partially_filled', $order->fresh()->status);
        $this->assertSame(1, Trade::count());

        // Second reconciliation: CSL reports 100 executed.
        $this->reconcileWith($this->cslFixture('partial_fill_completed'));

        // Third reconciliation with the same payload must not duplicate fills.
        $this->reconcileWith($this->cslFixture('partial_fill_completed'));

        $order = $order->fresh();
        $trades = $order->trades()->orderBy('id')->get();

        $this->assertSame(100.0, (float) $order->filled_quantity);
        $this->assertSame('filled', $order->status);
        $this->assertSame('matched', $order->reconciliation_status);

        /*
         * The second execution is the delta (60), never the cumulative total
         * (100): 40 + 60 = 100.
         */
        $this->assertSame(2, $trades->count());
        $this->assertSame(40.0, (float) $trades[0]->quantity);
        $this->assertSame(60.0, (float) $trades[1]->quantity);
        $this->assertSame(100.0, (float) $trades->sum('quantity'));

        $this->assertSame('CSL-FILL-'.$order->id.'-40', $trades[0]->reference);
        $this->assertSame('CSL-FILL-'.$order->id.'-100', $trades[1]->reference);

        // The reservation is fully consumed by the executed quantity.
        $this->assertSame(0.0, (float) Wallet::first()->locked);

        $portfolio = Portfolio::query()
            ->where('user_id', $user->id)
            ->where('symbol', 'TIP')
            ->firstOrFail();

        $this->assertSame(100.0, (float) $portfolio->quantity);
        $this->assertSame(100.0, (float) $portfolio->uncleared_quantity);
    }

    protected function reconcileWith(array $executedOrders): void
    {
        $client = Mockery::mock(CslTradeXClient::class);
        $client->shouldReceive('openOrders')->once()->with('TEST-MA-001')->andReturn([]);
        $client->shouldReceive('cancelledOrdersByDate')->once()->andReturn([]);
        $client->shouldReceive('executedOrders')->once()->with('TEST-MA-001')->andReturn($executedOrders);

        (new CslOrderReconciliationService($client))->reconcile('TEST-MA-001');
    }
}
