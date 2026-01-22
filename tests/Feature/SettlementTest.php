<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Portfolio;
use App\Models\Order;
use App\Models\Trade;
use App\Services\SettlementService;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_order_locks_funds(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 10000.00,
            'cleared_balance' => 10000.00,
            'uncleared_balance' => 0.00,
            'locked' => 0.00,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'ZENITH',
            'company' => 'Zenith Bank',
            'quantity' => 10,
            'units' => 10,
            'amount' => 100.00,
            'price' => 10.00,
            'market_price' => 10.00,
            'side' => 'buy',
            'type' => 'market',
            'market' => 'NGX',
            'currency' => 'NGN',
            'status' => 'open',
        ]);

        $trade = Trade::create([
            'order_id' => $order->id,
            'price' => 10.00,
            'quantity' => 10,
        ]);

        app(SettlementService::class)->settleOrder($order);

        $wallet->refresh();
        $portfolio = Portfolio::where('user_id', $user->id)->where('symbol', 'ZENITH')->first();

        $this->assertEquals(9900.00, $wallet->cleared_balance);
        $this->assertEquals(100.00, $wallet->locked);
        $this->assertNotNull($portfolio);
        $this->assertEquals(10, $portfolio->uncleared_quantity);
        $this->assertEquals(0, $portfolio->cleared_quantity);
    }

    public function test_sell_order_adds_uncleared_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 10000.00,
            'cleared_balance' => 10000.00,
            'uncleared_balance' => 0.00,
            'locked' => 0.00,
        ]);

        Portfolio::create([
            'user_id' => $user->id,
            'symbol' => 'ZENITH',
            'name' => 'Zenith Bank',
            'category' => 'local',
            'currency' => 'NGN',
            'market_price' => 10.00,
            'quantity' => 10,
            'cleared_quantity' => 10,
            'uncleared_quantity' => 0,
            'avg_price' => 10.00,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'ZENITH',
            'company' => 'Zenith Bank',
            'quantity' => 5,
            'units' => 5,
            'amount' => 50.00,
            'price' => 10.00,
            'market_price' => 10.00,
            'side' => 'sell',
            'type' => 'market',
            'market' => 'NGX',
            'currency' => 'NGN',
            'status' => 'open',
        ]);

        $trade = Trade::create([
            'order_id' => $order->id,
            'price' => 10.00,
            'quantity' => 5,
        ]);

        app(SettlementService::class)->settleOrder($order);

        $wallet->refresh();
        $portfolio = Portfolio::where('user_id', $user->id)->where('symbol', 'ZENITH')->first();

        $this->assertEquals(50.00, $wallet->uncleared_balance);
        $this->assertEquals(5, $portfolio->cleared_quantity);
        $this->assertEquals(5, $portfolio->uncleared_quantity);
    }

    public function test_settlement_command_processes_pending_trades(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 9900.00, // Cleared + uncleared
            'cleared_balance' => 9900.00,
            'uncleared_balance' => 0.00,
            'locked' => 100.00,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'ZENITH',
            'company' => 'Zenith Bank',
            'quantity' => 10,
            'units' => 10,
            'amount' => 100.00,
            'price' => 10.00,
            'market_price' => 10.00,
            'side' => 'buy',
            'type' => 'market',
            'market' => 'NGX',
            'currency' => 'NGN',
            'status' => 'open',
        ]);

        $trade = Trade::create([
            'order_id' => $order->id,
            'price' => 10.00,
            'quantity' => 10,
            'settlement_status' => 'pending',
            'settlement_date' => now()->subDay()->toDateString(), // Past date to trigger settlement
        ]);

        Portfolio::create([
            'user_id' => $user->id,
            'symbol' => 'ZENITH',
            'name' => 'Zenith Bank',
            'category' => 'local',
            'currency' => 'NGN',
            'market_price' => 10.00,
            'quantity' => 10,
            'cleared_quantity' => 0,
            'uncleared_quantity' => 10,
            'avg_price' => 10.00,
        ]);

        // Run settlement command
        $this->artisan('settlements:process')->assertSuccessful();

        // Refresh models
        $wallet->refresh();
        $portfolio = Portfolio::where('user_id', $user->id)->where('symbol', 'ZENITH')->first();
        $trade->refresh();

        // Assertions
        $this->assertEquals(9900.00, $wallet->cleared_balance); // Cash spent, locked removed
        $this->assertEquals(0.00, $wallet->locked);
        $this->assertEquals(9900.00, $wallet->balance);

        $this->assertEquals(10, $portfolio->cleared_quantity);
        $this->assertEquals(0, $portfolio->uncleared_quantity);
        $this->assertEquals(10, $portfolio->quantity);

        $this->assertEquals('settled', $trade->settlement_status);
    }
}