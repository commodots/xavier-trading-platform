<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_service_uses_finnhub_provider_for_quotes(): void
    {
        putenv('MARKET_PROVIDER=finnhub');
        putenv('FINNHUB_API_KEY=test_key');

        Http::fake([
            'https://finnhub.io/api/v1/quote*' => Http::response([
                'c' => 224.72,
                'h' => 225.50,
                'l' => 220.10,
                'o' => 221.90,
                'pc' => 223.65,
            ], 200),
        ]);

        $service = app(MarketService::class);
        $price = $service->quote('AAPL');

        $this->assertSame(224.72, $price);
    }

    public function test_market_quotes_endpoint_returns_quote_details(): void
    {
        putenv('MARKET_PROVIDER=finnhub');
        putenv('FINNHUB_API_KEY=test_key');

        Http::fake([
            'https://finnhub.io/api/v1/quote*' => Http::response([
                'c' => 175.60,
                'h' => 177.20,
                'l' => 173.10,
                'o' => 174.00,
                'pc' => 173.40,
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/market/quotes?symbols=AAPL,TSLA');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => [['symbol', 'price', 'change', 'previous_close', 'timestamp']]]);
        $response->assertJson(['success' => true]);

        $quotes = $response->json('data');
        $this->assertCount(2, $quotes);
        $this->assertSame('AAPL', $quotes[0]['symbol']);
        $this->assertSame(175.6, $quotes[0]['price']);
        $this->assertSame(1.27, $quotes[0]['change']);
    }

    public function test_bracket_order_requires_take_profit_and_stop_loss(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/trade/place', [
            'symbol' => 'AAPL',
            'qty' => 1,
            'side' => 'buy',
            'type' => 'bracket',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Bracket orders require both take profit and stop loss']);
    }
}
