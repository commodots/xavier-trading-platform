<?php

namespace Tests\Feature\Alpaca;

use App\Providers\AlpacaProvider;
use App\Services\Stocks\AlpacaMarketDataProvider;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AlpacaMarketDataTest extends TestCase
{
    public function test_quote_is_normalised_into_the_shared_market_shape(): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('quoteDetails')->once()->with('AAPL')->andReturn([
            'symbol' => 'AAPL',
            'price' => 205.0,
            'previous_close' => 200.0,
            'change' => 2.5,
            'timestamp' => '2026-01-02T15:00:00+00:00',
        ]);

        $quote = (new AlpacaMarketDataProvider($provider))->quote('AAPL');

        $this->assertSame('AAPL', $quote['symbol']);
        $this->assertSame('GLOBAL', $quote['market']);
        $this->assertSame(205.0, $quote['price']);
        $this->assertSame(200.0, $quote['previous_close']);
        $this->assertSame(5.0, $quote['change']);
        $this->assertSame(2.5, $quote['change_percent']);
        $this->assertSame('alpaca', $quote['provider']);

        // Alpaca has no NGX-style bid/offer ladder on this endpoint.
        $this->assertNull($quote['bid_price']);
        $this->assertNull($quote['offer_price']);
    }

    public function test_history_maps_alpaca_bars_into_chart_points(): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('bars')->once()->with('AAPL', 30)->andReturn([
            'bars' => [
                [
                    't' => '2026-01-02T05:00:00Z',
                    'o' => 200,
                    'h' => 210,
                    'l' => 198,
                    'c' => 205,
                    'v' => 1200,
                ],
            ],
        ]);

        $history = (new AlpacaMarketDataProvider($provider))->historical('AAPL', '30d');

        $this->assertSame('AAPL', $history['symbol']);
        $this->assertSame('GLOBAL', $history['market']);
        $this->assertSame('alpaca', $history['provider']);
        $this->assertCount(1, $history['data']);

        $this->assertSame('2026-01-02T05:00:00Z', $history['data'][0]['date']);
        $this->assertSame(200.0, $history['data'][0]['open']);
        $this->assertSame(205.0, $history['data'][0]['close']);
        $this->assertSame(1200.0, $history['data'][0]['volume']);
    }

    public function test_history_falls_back_to_an_empty_chart_when_alpaca_fails(): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('bars')->once()->andThrow(
            new RuntimeException('Alpaca bars request failed. HTTP 503')
        );

        $history = (new AlpacaMarketDataProvider($provider))->historical('AAPL', '7d');

        // A chart is not worth failing a page render over.
        $this->assertSame([], $history['data']);
        $this->assertSame('GLOBAL', $history['market']);
    }

    public function test_bars_returns_an_empty_envelope_without_credentials(): void
    {
        config()->set('services.alpaca.api_key', null);
        config()->set('services.alpaca.secret_key', null);

        $this->assertSame(['bars' => []], app(AlpacaProvider::class)->bars('AAPL', 7));
    }
}
