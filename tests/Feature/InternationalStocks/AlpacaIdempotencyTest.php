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

class AlpacaIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_ID = 'b1f2c3d4-0001-4a1b-8c2d-000000000001';

    public function test_replayed_fill_payloads_book_a_single_trade(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $payload = [$this->alpacaFixture('executed_order')];

        $this->reconcileWith($payload);
        $this->reconcileWith($payload);
        $this->reconcileWith($payload);

        $order = $order->fresh();

        $this->assertSame(2.5, (float) $order->filled_quantity);
        $this->assertSame('filled', $order->status);
        $this->assertSame(1, Trade::count());

        // Funds and holdings were only moved once.
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(4500.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
        $this->assertSame(2.5, (float) Portfolio::query()->firstOrFail()->quantity);
    }

    public function test_webhook_redelivery_is_idempotent(): void
    {
        $user = $this->userWithWallet();
        $order = $this->alpacaOrder($user);

        $payload = [
            'event' => 'fill',
            'order' => $this->alpacaFixture('executed_order'),
        ];

        $this->postJson('/api/alpaca/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->postJson('/api/alpaca/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSame(1, Trade::count());
        $this->assertSame(2.5, (float) $order->fresh()->filled_quantity);
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
    }

    public function test_webhook_ignores_events_for_unknown_orders(): void
    {
        $this->postJson('/api/alpaca/webhook', [
            'event' => 'fill',
            'order' => $this->alpacaFixture('executed_order'),
        ])
            ->assertOk()
            ->assertExactJson(['ignored']);

        $this->assertSame(0, Trade::count());
    }

    public function test_webhook_cancel_event_confirms_the_cancellation_and_refunds(): void
    {
        $user = $this->userWithWallet();
        $this->alpacaOrder($user);

        $this->postJson('/api/alpaca/webhook', [
            'event' => 'canceled',
            'order' => $this->alpacaFixture('order_canceled'),
        ])->assertOk();

        $order = Order::query()->firstOrFail();

        $this->assertSame('canceled', $order->status);
        $this->assertSame('confirmed', $order->provider_cancellation_status);
        $this->assertSame(0.0, (float) Wallet::query()->firstOrFail()->locked);
        $this->assertSame(5000.0, (float) Wallet::query()->firstOrFail()->usd_cleared);
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
