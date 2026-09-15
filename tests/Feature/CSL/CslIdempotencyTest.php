<?php

namespace Tests\Feature\CSL;

use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslOrderReconciliationService;
use App\Services\CSL\CslTradeXClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciling_the_same_execution_twice_creates_one_trade_and_one_fill(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 0,
            'locked' => 1000,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'TEST',
            'side' => 'buy',
            'type' => 'limit',
            'price' => 100,
            'quantity' => 10,
            'amount' => 1000,
            'market_price' => 100,
            'filled_quantity' => 0,
            'status' => 'open',
            'provider' => 'csl',
            'provider_market_account_id' => 'ACC-1',
            'provider_submitted_at' => now(),
            'currency' => 'NGN',
        ]);

        $client = Mockery::mock(CslTradeXClient::class);
        $client->shouldReceive('openOrders')->twice()->with('ACC-1')->andReturn([]);
        $client->shouldReceive('cancelledOrdersByDate')->twice()->andReturn([]);
        $client->shouldReceive('executedOrders')->twice()->with('ACC-1')->andReturn([
            'result' => [[
                'order_id' => 'CSL-1',
                'symbol_code' => 'TEST',
                'order_side' => 'buy',
                'order_quantity' => 10,
                'filled_quantity' => 10,
                'order_status' => 'filled',
                'average_fill_price' => 100,
                'trade_id' => 'TRADE-1',
            ]],
        ]);

        $service = new CslOrderReconciliationService($client);
        $service->reconcile('ACC-1');
        $service->reconcile('ACC-1');

        $this->assertSame(1, $order->trades()->count());
        $this->assertSame(10.0, (float) $order->fresh()->filled_quantity);
        $this->assertSame(10.0, (float) $order->trades()->first()->quantity);
    }
}
