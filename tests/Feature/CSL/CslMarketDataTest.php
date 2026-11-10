<?php

namespace Tests\Feature\CSL;

use App\Models\KycProfile;
use App\Models\User;
use App\Services\CSL\CslMarketDataProvider;
use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use App\Services\Demo\DemoTradingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslMarketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_normalizes_a_raw_csl_market_payload(): void
    {
        $xt = Mockery::mock(CslTradeXClient::class);
        $xt->shouldReceive('stockPrice')->once()->with('TIP')->andReturn($this->cslFixture('quote'));

        $provider = new CslMarketDataProvider(
            $xt,
            Mockery::mock(CslStockClient::class)
        );

        $quote = $provider->quote('TIP');

        $this->assertSame('TIP', $quote['symbol']);
        $this->assertSame('Test Instrument', $quote['name']);
        $this->assertSame('NGX', $quote['market']);
        $this->assertSame(105.0, $quote['price']);
        $this->assertSame(100.0, $quote['previous_close']);
        $this->assertSame(102.0, $quote['open']);
        $this->assertSame(108.0, $quote['high']);
        $this->assertSame(101.0, $quote['low']);
        $this->assertSame(5.0, $quote['change']);
        $this->assertSame(5.0, $quote['change_percent']);
        $this->assertSame(104.0, $quote['bid_price']);
        $this->assertSame(1000, $quote['bid_quantity']);
        $this->assertSame(106.0, $quote['offer_price']);
        $this->assertSame(1000, $quote['offer_quantity']);
        $this->assertSame(5000, $quote['volume']);
        $this->assertSame(525000, $quote['value']);
        $this->assertSame('csl', $quote['provider']);
    }

    public function test_stock_prices_and_market_leaders_are_normalized_from_csl(): void
    {
        $xt = Mockery::mock(CslTradeXClient::class);
        $xt->shouldReceive('stockPrices')->once()->andReturn([
            'result' => [
                [
                    'GetCRXTMarketStockPrices' => [
                        [
                            'symbol_code' => 'TIP',
                            'symbol_description' => 'Test Instrument',
                            'current_price' => 105,
                            'price_difference_today' => 5,
                            'percent_difference_today' => 5,
                            'traded_volume' => 5000,
                        ],
                    ],
                ],
            ],
        ]);
        $xt->shouldReceive('topGainers')->once()->andReturn([
            'GetCRXTMarketTopGainers' => [[
                'symbol_code' => 'TIP',
                'symbol_description' => 'Test Instrument',
                'current_price' => 105,
                'percent_difference_today' => 5,
            ]],
        ]);
        $xt->shouldReceive('topLosers')->once()->andReturn([
            'GetCRXTMarketTopLosers' => [[
                'symbol_code' => 'LOSS',
                'symbol_description' => 'Loss Corp',
                'current_price' => 50,
                'percent_difference_today' => -2,
            ]],
        ]);

        $provider = new CslMarketDataProvider(
            $xt,
            Mockery::mock(CslStockClient::class)
        );

        $prices = $provider->stockPrices();

        $this->assertSame(105.0, $prices[0]['price']);
        $this->assertSame(5.0, $prices[0]['change_percent']);
        $this->assertSame('TIP', $provider->topGainers()[0]['symbol']);
        $this->assertSame(-2.0, $provider->topLosers()[0]['change_percent']);
    }

    public function test_order_book_normalizes_csl_bids_offers_and_market_depth(): void
    {
        $xt = Mockery::mock(CslTradeXClient::class);
        $xt->shouldReceive('topBids')->once()->with('TIP')->andReturn([
            'GetCRXTMarketTopBids' => [['top_bid_price' => 104, 'top_bid_quantity' => 1000]],
        ]);
        $xt->shouldReceive('topOffers')->once()->with('TIP')->andReturn([
            'GetCRXTMarketTopOffers' => [['top_offer_price' => 106, 'top_offer_quantity' => 900]],
        ]);
        $xt->shouldReceive('marketDepthPurchase')->once()->andReturn([
            'GetCRXTMarketDepthPurchase' => [['price' => 104, 'quantity' => 1000]],
        ]);
        $xt->shouldReceive('marketDepthSale')->once()->andReturn([
            'GetCRXTMarketDepthSale' => [['price' => 106, 'quantity' => 900]],
        ]);

        $provider = new CslMarketDataProvider(
            $xt,
            Mockery::mock(CslStockClient::class)
        );

        $book = $provider->orderBook('TIP');

        $this->assertSame(104.0, $book['bids'][0]['price']);
        $this->assertSame(1000, $book['bids'][0]['quantity']);
        $this->assertSame(106.0, $book['offers'][0]['price']);
        $this->assertSame(900, $book['sale_depth'][0]['quantity']);
    }

    public function test_historical_prices_are_requested_from_csl_for_the_selected_range(): void
    {
        Carbon::setTestNow('2026-11-10');

        try {
            $stockClient = Mockery::mock(CslStockClient::class);
            $stockClient->shouldReceive('stockByDate')
                ->once()
                ->with('TIP', '2026-11-03', '2026-11-10')
                ->andReturn([
                    'result' => [[
                        'GetCRSTStockByDate' => [
                            ['trade_date' => '2026-11-10', 'current_price' => 105],
                        ],
                    ]],
                ]);

            $provider = new CslMarketDataProvider(
                Mockery::mock(CslTradeXClient::class),
                $stockClient
            );

            $this->assertSame(
                [['trade_date' => '2026-11-10', 'current_price' => 105]],
                $provider->historical('TIP', '1w')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_all_history_requests_the_full_csl_date_range(): void
    {
        Carbon::setTestNow('2026-11-10');

        try {
            $stockClient = Mockery::mock(CslStockClient::class);
            $stockClient->shouldReceive('stockByDate')
                ->once()
                ->with('TIP', '1900-01-01', '2026-11-10')
                ->andReturn(['GetCRSTStockByDate' => []]);

            $provider = new CslMarketDataProvider(
                Mockery::mock(CslTradeXClient::class),
                $stockClient
            );

            $this->assertSame([], $provider->historical('TIP', 'all'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_csl_mock_mode_has_fixtures_for_all_ngx_market_data_endpoints(): void
    {
        config()->set('services.csl.mock', true);

        $provider = app(CslMarketDataProvider::class);

        $this->assertSame(105.0, $provider->quote('TIP')['price']);
        $this->assertCount(2, $provider->stockPrices());
        $this->assertSame('TIP', $provider->topGainers()[0]['symbol']);
        $this->assertSame('LOSS', $provider->topLosers()[0]['symbol']);

        $orderBook = $provider->orderBook('TIP');
        $this->assertSame(104.0, $orderBook['bids'][0]['price']);
        $this->assertSame(106.0, $orderBook['offers'][0]['price']);
        $this->assertCount(2, $orderBook['purchase_depth']);
        $this->assertCount(2, $orderBook['sale_depth']);
        $this->assertCount(2, $provider->historical('TIP', '1w'));
    }

    public function test_ngx_market_api_routes_use_the_csl_market_data_provider(): void
    {
        $provider = Mockery::mock(CslMarketDataProvider::class);
        $provider->shouldReceive('stockPrices')->twice()->andReturn([[
            'symbol' => 'TIP',
            'name' => 'Test Instrument',
            'price' => 105.0,
            'change_percent' => 5.0,
            'volume' => 5000,
        ]]);
        $provider->shouldReceive('topGainers')->once()->andReturn([[
            'symbol' => 'TIP',
            'name' => 'Test Instrument',
            'price' => 105.0,
            'change_percent' => 5.0,
            'volume' => 5000,
        ]]);
        $provider->shouldReceive('topLosers')->once()->andReturn([[
            'symbol' => 'LOSS',
            'name' => 'Loss Corp',
            'price' => 50.0,
            'change_percent' => -2.0,
            'volume' => 10,
        ]]);
        $provider->shouldReceive('quote')->once()->with('TIP')->andReturn([
            'symbol' => 'TIP',
            'name' => 'Test Instrument',
            'price' => 105.0,
            'provider' => 'csl',
        ]);
        $provider->shouldReceive('orderBook')->once()->with('TIP')->andReturn([
            'bids' => [['price' => 104.0, 'quantity' => 1000]],
            'offers' => [],
            'purchase_depth' => [],
            'sale_depth' => [],
        ]);
        $provider->shouldReceive('historical')
            ->once()
            ->with('TIP', 'custom', '2026-11-01', '2026-11-10')
            ->andReturn([['trade_date' => '2026-11-10', 'current_price' => 105]]);

        $this->app->instance(CslMarketDataProvider::class, $provider);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/market/ngx/prices')
            ->assertOk()
            ->assertJsonPath('data.0.symbol', 'TIP')
            ->assertJsonPath('data.0.price', 105);

        $this->actingAs($user)
            ->getJson('/api/market/ngx/insights')
            ->assertOk()
            ->assertJsonPath('data.gainers.0.symbol', 'TIP')
            ->assertJsonPath('data.gainers.0.change', 5)
            ->assertJsonPath('data.losers.0.symbol', 'LOSS');

        $this->actingAs($user)
            ->getJson('/api/market/ngx/quote/TIP')
            ->assertOk()
            ->assertJsonPath('data.price', 105);

        $this->actingAs($user)
            ->getJson('/api/market/ngx/order-book/TIP')
            ->assertOk()
            ->assertJsonPath('data.bids.0.price', 104);

        $this->actingAs($user)
            ->getJson('/api/market/ngx/history/TIP?range=custom&start=2026-11-01&end=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.data.0.close', 105);
    }

    public function test_ngx_order_uses_a_fresh_csl_quote_for_order_pricing(): void
    {
        $provider = Mockery::mock(CslMarketDataProvider::class);
        $provider->shouldReceive('quote')->once()->with('TIP')->andReturn([
            'symbol' => 'TIP',
            'name' => 'CSL Instrument',
            'price' => 200.0,
            'provider' => 'csl',
        ]);
        $this->app->instance(CslMarketDataProvider::class, $provider);

        $user = User::factory()->create([
            'verification_level' => 2,
            'trading_mode' => 'demo',
        ]);
        KycProfile::create([
            'user_id' => $user->id,
            'level' => 'full',
            'status' => 'verified',
            'tier' => 2,
        ]);

        $demoTrading = Mockery::mock(DemoTradingService::class);
        $demoTrading->shouldReceive('executeTrade')
            ->once()
            ->with(
                $user,
                Mockery::on(fn (array $data) => $data['market_price'] === 200.0
                    && $data['company'] === 'CSL Instrument'
                    && $data['amount'] === 1000.0)
            )
            ->andReturn(['id' => 1]);
        $this->app->instance(DemoTradingService::class, $demoTrading);

        $this->actingAs($user)
            ->postJson('/api/orders', [
                'market' => 'NGX',
                'symbol' => 'TIP',
                'company' => 'Client-supplied name',
                'market_price' => 100,
                'amount' => 500,
                'side' => 'buy',
                'quantity' => 5,
                'type' => 'market',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }
}
