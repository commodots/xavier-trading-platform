<?php

namespace Tests\Feature\Alpaca;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use App\Services\Stocks\AlpacaOrderReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AlpacaPartialFillTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_ID = 'b1f2c3d4-0001-4a1b-8c2d-000000000001';

    public function test_partial_fill_creates_only_the_newly_executed_quantity(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        // First reconciliation: Alpaca reports 1.5 of 2.5 executed.
        $this->reconcileWith([$this->alpacaFixture('partial_fill')]);

        $this->assertSame(1.5, (float) $order->fresh()->filled_quantity);
        $this->assertSame('partially_filled', $order->fresh()->status);
        $this->assertSame(1, Trade::count());

        // 500 reserved, 300 consumed by the 1.5 executed shares.
        $this->assertSame(200.0, (float) Wallet::query()->firstOrFail()->locked);

        // Second reconciliation: Alpaca reports the full 2.5.
        $this->reconcileWith([$this->alpacaFixture('partial_fill_completed')]);

        // Third reconciliation with the same payload must not duplicate fills.
        $this->reconcileWith([$this->alpacaFixture('partial_fill_completed')]);

        $order = $order->fresh();
        $trades = $order->trades()->orderBy('id')->get();

        $this->assertSame(2.5, (float) $order->filled_quantity);
        $this->assertSame('filled', $order->status);
        $this->assertSame('matched', $order->reconciliation_status);

        /**
         * The second execution is the delta (1.0), never the cumulative total
         * (2.5): 1.5 + 1.0 = 2.5.
         */
        $this->assertSame(2, $trades->count());
        $this->assertSame(1.5, (float) $trades[0]->quantity);
        $this->assertSame(1.0, (float) $trades[1]->quantity);
        $this->assertSame(2.5, (float) $trades->sum('quantity'));

        $this->assertSame('ALPACA-FILL-'.$order->id.'-1.5', $trades[0]->reference);
        $this->assertSame('ALPACA-FILL-'.$order->id.'-2.5', $trades[1]->reference);

        // The reservation is fully consumed by the executed quantity.
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);

        $portfolio = Portfolio::query()->firstOrFail();
        $this->assertSame(2.5, (float) $portfolio->quantity);
        $this->assertSame(2.5, (float) $portfolio->uncleared_quantity);
    }

    private function reconcileWith(array $providerOrders): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('orders')->once()->andReturn($providerOrders);

        (new AlpacaOrderReconciliationService($provider))->reconcile();
    }

    private function userWithWallet(): User
    {
        $user = User::factory()->create();

        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance' => 5000,
            'usd_cleared' => 4500,
            'usd_uncleared' => 0,
            'locked' => 500,
            'status' => 'active',
        ]);

        return $user;
    }

    private function alpacaOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'symbol' => 'AAPL',
            'side' => 'buy',
            'type' => 'market',
            'price' => 200,
            'quantity' => 2.5,
            'amount' => 500,
            'market_price' => 200,
            'company' => 'Apple Inc.',
            'filled_quantity' => 0,
            'status' => 'open',
            'market' => 'GLOBAL',
            'currency' => 'USD',
            'provider' => 'alpaca',
            'provider_order_id' => self::ORDER_ID,
            'provider_client_reference' => 'XAV-20260101120000-testref',
            'provider_submitted_at' => now(),
            'reconciliation_status' => 'pending',
        ], $overrides));
    }
}
