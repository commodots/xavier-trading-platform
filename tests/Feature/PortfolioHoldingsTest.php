<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioHoldingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_portfolio_derives_crypto_holdings_from_open_trades(): void
    {
        $user = User::factory()->create();

        putenv('MARKET_PROVIDER=finnhub');
        putenv('FINNHUB_API_KEY=test_key');

        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'usd_cleared' => 2000,
            'usd_uncleared' => 0,
            'balance' => 2000,
            'locked' => 0,
        ]);

        $orderA = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'type' => 'market',
            'status' => 'filled',
            'currency' => 'USD',
            'market' => 'CRYPTO',
            'amount' => 100,
            'market_price' => 100,
            'quantity' => 1,
            'filled_quantity' => 1,
        ]);

        Trade::create([
            'order_id' => $orderA->id,
            'user_id' => $user->id,
            'pair' => 'BTC/USDT',
            'type' => 'buy',
            'amount' => 100,
            'quantity' => 1,
            'price' => 100,
            'entry_price' => 100,
            'status' => 'open',
        ]);

        $orderB = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'type' => 'market',
            'status' => 'filled',
            'currency' => 'USD',
            'market' => 'CRYPTO',
            'amount' => 75,
            'market_price' => 150,
            'quantity' => 0.5,
            'filled_quantity' => 0.5,
        ]);

        Trade::create([
            'order_id' => $orderB->id,
            'user_id' => $user->id,
            'pair' => 'BTC/USDT',
            'type' => 'buy',
            'amount' => 75,
            'quantity' => 0.5,
            'price' => 150,
            'entry_price' => 150,
            'status' => 'open',
        ]);

        Http::fake([
            'https://finnhub.io/api/v1/quote*' => Http::response(['c' => 0], 200),
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['usd' => 160],
            ], 200),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio');

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'mode' => 'live']);

        $payload = $response->json('data');
        $this->assertEqualsWithDelta(240.0, $payload['crypto_value_usd'], 0.0001);

        $btcHolding = collect($payload['holdings'])->firstWhere('symbol', 'BTC');
        $this->assertNotNull($btcHolding);
        $this->assertEqualsWithDelta(1.5, $btcHolding['quantity'], 0.0001);
        $this->assertEqualsWithDelta(116.6666666667, $btcHolding['avg_price'], 0.0001);
        $this->assertEqualsWithDelta(160.0, $btcHolding['current_price'], 0.0001);
        $this->assertEqualsWithDelta(175.0, $btcHolding['total_cost'], 0.0001);
        $this->assertEqualsWithDelta(240.0, $btcHolding['current_value'], 0.0001);
        $this->assertEqualsWithDelta(65.0, $btcHolding['profit_loss'], 0.0001);
    }

    public function test_portfolio_excludes_closed_trades_from_holdings(): void
    {
        putenv('MARKET_PROVIDER=finnhub');
        putenv('FINNHUB_API_KEY=test_key');

        $user = User::factory()->create();

        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'usd_cleared' => 1000,
            'usd_uncleared' => 0,
            'balance' => 1000,
            'locked' => 0,
        ]);

        $openOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'side' => 'buy',
            'type' => 'market',
            'status' => 'filled',
            'currency' => 'USD',
            'market' => 'CRYPTO',
            'amount' => 100,
            'market_price' => 100,
            'quantity' => 1,
            'filled_quantity' => 1,
        ]);

        Trade::create([
            'order_id' => $openOrder->id,
            'user_id' => $user->id,
            'pair' => 'ETH/USDT',
            'type' => 'buy',
            'amount' => 100,
            'quantity' => 1,
            'price' => 100,
            'entry_price' => 100,
            'status' => 'open',
        ]);

        $closedOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'side' => 'buy',
            'type' => 'market',
            'status' => 'filled',
            'currency' => 'USD',
            'market' => 'CRYPTO',
            'amount' => 50,
            'market_price' => 50,
            'quantity' => 0.5,
            'filled_quantity' => 0.5,
        ]);

        Trade::create([
            'order_id' => $closedOrder->id,
            'user_id' => $user->id,
            'pair' => 'ETH/USDT',
            'type' => 'buy',
            'amount' => 50,
            'quantity' => 0.5,
            'price' => 50,
            'entry_price' => 50,
            'status' => 'closed',
        ]);

        Http::fake([
            'https://finnhub.io/api/v1/quote*' => Http::response(['c' => 0], 200),
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'ethereum' => ['usd' => 120],
            ], 200),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio');

        $response->assertStatus(200);
        $payload = $response->json('data');

        $ethHolding = collect($payload['holdings'])->firstWhere('symbol', 'ETH');
        $this->assertNotNull($ethHolding);
        $this->assertEqualsWithDelta(1.0, $ethHolding['quantity'], 0.0001);
        $this->assertEqualsWithDelta(120.0, $ethHolding['current_price'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $ethHolding['total_cost'], 0.0001);
    }
}
