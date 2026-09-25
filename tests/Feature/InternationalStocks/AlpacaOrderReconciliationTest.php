<?php

namespace Tests\Feature\Alpaca;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderSyncLog;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use App\Services\Stocks\AlpacaOrderReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AlpacaOrderReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_ID = 'b1f2c3d4-0001-4a1b-8c2d-000000000001';

    public function test_executed_order_is_matched_and_booked_as_a_trade(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $summary = $this->reconcileWith([$this->alpacaFixture('executed_order')]);

        $this->assertSame(['orders' => 1, 'trades' => 1], $summary);

        $order = $order->fresh();
        $this->assertSame('filled', $order->status);
        $this->assertSame(2.5, (float) $order->filled_quantity);
        $this->assertSame('matched', $order->reconciliation_status);
        $this->assertNotNull($order->last_reconciled_at);
        $this->assertSame('filled', $order->provider_response['status']);

        $trade = Trade::query()->firstOrFail();
        $this->assertSame('alpaca', $trade->provider);
        $this->assertSame('ALPACA-FILL-'.$order->id.'-2.5', $trade->reference);
        $this->assertSame(2.5, (float) $trade->quantity);
        $this->assertSame(200.0, (float) $trade->price);
        $this->assertSame(500.0, (float) $trade->amount);
        $this->assertSame('pending', $trade->settlement_status);
        $this->assertFalse((bool) $trade->is_settled);

        /**
         * The reserved USD was spent on the shares, so it leaves the wallet
         * instead of being handed back to the cleared balance.
         */
        $wallet = Wallet::query()->firstOrFail();
        $this->assertSame(0.0, (float) $wallet->locked);
        $this->assertSame(4500.0, (float) $wallet->usd_cleared);

        $portfolio = Portfolio::query()->firstOrFail();
        $this->assertSame(2.5, (float) $portfolio->quantity);
        $this->assertSame(2.5, (float) $portfolio->uncleared_quantity);
        $this->assertSame(0.0, (float) $portfolio->cleared_quantity);
        $this->assertSame(200.0, (float) $portfolio->avg_price);
        $this->assertSame('foreign', $portfolio->category);
        $this->assertSame('USD', $portfolio->currency);
    }

    public function test_order_is_discovered_by_the_xavier_client_reference(): void
    {
        $user = $this->userWithWallet();

        $order = $this->alpacaOrder($user, [
            'provider_order_id' => null,
            'alpaca_order_id' => null,
        ]);

        $this->reconcileWith([$this->alpacaFixture('executed_order')]);

        $order = $order->fresh();

        $this->assertSame('matched', $order->reconciliation_status);
        $this->assertSame(self::ORDER_ID, $order->provider_order_id);
        $this->assertSame(2.5, (float) $order->filled_quantity);
        $this->assertSame(1, Trade::count());
    }

    public function test_an_order_alpaca_does_not_report_is_left_unmatched(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $summary = $this->reconcileWith([]);

        $this->assertSame(['orders' => 0, 'trades' => 0], $summary);

        $order = $order->fresh();

        // Guessing that nothing executed would be worse than flagging it.
        $this->assertSame('unmatched', $order->reconciliation_status);
        $this->assertSame('open', $order->status);
        $this->assertSame(0.0, (float) $order->filled_quantity);
        $this->assertSame(500.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(0, Trade::count());

        $log = ProviderSyncLog::query()->firstOrFail();
        $this->assertSame('alpaca', $log->provider);
        $this->assertSame('unmatched', $log->status);
    }

    public function test_orders_owned_by_another_provider_are_never_reconciled(): void
    {
        $user = $this->userWithWallet();

        $cslOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'AAPL',
            'side' => 'buy',
            'type' => 'market',
            'price' => 200,
            'quantity' => 2.5,
            'filled_quantity' => 0,
            'status' => 'open',
            'market' => 'NGX',
            'currency' => 'NGN',
            'provider' => 'csl',
            'provider_order_id' => self::ORDER_ID,
            'provider_client_reference' => 'XAV-20260101120000-testref',
            'provider_submitted_at' => now(),
        ]);

        $this->assertSame(['orders' => 0, 'trades' => 0], $this->reconcileWith([
            $this->alpacaFixture('executed_order'),
        ]));

        $cslOrder = $cslOrder->fresh();

        $this->assertSame('open', $cslOrder->status);
        $this->assertSame(0.0, (float) $cslOrder->filled_quantity);
        $this->assertNull($cslOrder->reconciliation_status);
        $this->assertSame(0, Trade::count());
        $this->assertSame(0, ProviderSyncLog::count());
    }

    private function reconcileWith(array $providerOrders): array
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('orders')->once()->andReturn($providerOrders);

        return (new AlpacaOrderReconciliationService($provider))->reconcile();
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
