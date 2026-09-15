<?php

namespace Tests\Feature\CSL;

use App\Models\Order;
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

    public function test_partial_fill_is_recorded_once_and_order_status_is_updated(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 1000,
            'ngn_cleared' => 1000,
            'ngn_uncleared' => 0,
            'locked' => 0,
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
            'provider_order_id' => 'CSL-2',
            'provider_market_account_id' => 'ACC-1',
            'provider_submitted_at' => now(),
            'currency' => 'NGN',
        ]);

        $client = Mockery::mock(CslTradeXClient::class);
        $client->shouldReceive('openOrders')->once()->with('ACC-1')->andReturn([]);
        $client->shouldReceive('cancelledOrdersByDate')->once()->andReturn([]);
        $client->shouldReceive('executedOrders')->once()->with('ACC-1')->andReturn($this->cslFixture('partial_fill'));

        $service = new CslOrderReconciliationService($client);
        $service->reconcile('ACC-1');

        $this->assertSame('partially_filled', $order->fresh()->status);
        $this->assertSame(6.0, (float) $order->fresh()->filled_quantity);
        $this->assertSame(1, Trade::count());
        $this->assertSame(6.0, (float) Trade::first()->quantity);
    }
}
