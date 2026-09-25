<?php

namespace Tests\Feature\Alpaca;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use App\Services\Stocks\AlpacaOrderReconciliationService;
use App\Services\Stocks\AlpacaOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AlpacaCancellationTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_ID = 'b1f2c3d4-0001-4a1b-8c2d-000000000001';

    public function test_cancellation_is_only_confirmed_once_alpaca_reports_it(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('cancelOrder')->once()->with(self::ORDER_ID)->andReturn([
            'id' => self::ORDER_ID,
            'status' => 'canceled',
        ]);

        // Alpaca no longer lists the order, so the outcome is not established.
        $provider->shouldReceive('orders')->once()->andReturn([]);

        (new AlpacaOrderService(
            $provider,
            new AlpacaOrderReconciliationService($provider)
        ))->cancel($order);

        $order = $order->fresh();

        $this->assertSame('unknown', $order->provider_cancellation_status);
        $this->assertSame('cancel_requested', $order->status);
        $this->assertSame('unmatched', $order->reconciliation_status);

        // Nothing is released on an unconfirmed cancellation.
        $this->assertSame(500.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(4500.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
        $this->assertSame(0, Trade::count());
    }

    public function test_the_confirmed_cancellation_returns_the_reservation(): void
    {
        $user = $this->userWithWallet();

        $order = $this->alpacaOrder($user, [
            'provider_cancellation_status' => 'requested',
        ]);

        $this->reconcileWith([$this->alpacaFixture('order_canceled')]);

        $order = $order->fresh();

        $this->assertSame('canceled', $order->status);
        $this->assertSame('confirmed', $order->provider_cancellation_status);
        $this->assertSame('matched', $order->reconciliation_status);
        $this->assertSame(0, Trade::count());

        // The whole reservation goes back to the cleared balance.
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(5000.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
    }

    public function test_cancellation_after_a_partial_fill_releases_only_the_remainder(): void
    {
        $user = $this->userWithWallet();

        // 1.5 of 2.5 executed (300 of the 500 reservation already spent).
        $order = $this->alpacaOrder($user, [
            'filled_quantity' => 1.5,
            'status' => 'partially_filled',
            'provider_cancellation_status' => 'requested',
        ]);

        Wallet::query()->firstOrFail()->update(['locked' => 200]);

        $this->reconcileWith([$this->alpacaFixture('canceled_after_partial_fill')]);

        $order = $order->fresh();

        $this->assertSame('canceled', $order->status);
        $this->assertSame(1.5, (float) $order->filled_quantity);
        $this->assertSame('confirmed', $order->provider_cancellation_status);

        // The executed quantity was already booked, this payload adds no fill.
        $this->assertSame(0, Trade::count());
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(4700.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
    }

    public function test_a_provider_rejection_is_stored_as_a_terminal_rejected_order(): void
    {
        $user = $this->userWithWallet();

        $order = $this->alpacaOrder($user, [
            'provider_cancellation_status' => 'requested',
        ]);

        $this->reconcileWith([$this->alpacaFixture('order_rejected')]);

        $order = $order->fresh();

        // Provider rejection is a terminal execution outcome, not a
        // confirmed cancellation state transition.
        $this->assertSame('rejected', $order->status);
        $this->assertSame('requested', $order->provider_cancellation_status);
        $this->assertSame(0, Trade::count());
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(5000.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
    }

    public function test_a_cancellation_refused_by_alpaca_releases_nothing(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('cancelOrder')->once()->andReturn([
            'id' => self::ORDER_ID,
            'status' => 'rejected',
        ]);

        try {
            (new AlpacaOrderService(
                $provider,
                new AlpacaOrderReconciliationService($provider)
            ))->cancel($order);

            $this->fail('A refused cancellation must surface to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Alpaca rejected the order cancellation request.',
                $exception->getMessage()
            );
        }

        $this->assertSame('rejected', $order->fresh()->provider_cancellation_status);
        $this->assertSame(500.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(0, Trade::count());
    }

    public function test_the_alpaca_cancel_service_refuses_orders_it_does_not_own(): void
    {
        $user = $this->userWithWallet();

        $order = $this->alpacaOrder($user, [
            'provider' => 'csl',
            'provider_order_id' => 'CSL-100001',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order is not an Alpaca order.');

        (new AlpacaOrderService(
            Mockery::mock(AlpacaProvider::class),
            Mockery::mock(AlpacaOrderReconciliationService::class)
        ))->cancel($order);
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
