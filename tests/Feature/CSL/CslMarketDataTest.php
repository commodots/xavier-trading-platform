<?php

namespace Tests\Feature\CSL;

use App\Services\CSL\CslMarketDataProvider;
use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use Mockery;
use Tests\TestCase;

class CslMarketDataTest extends TestCase
{
    public function test_quote_accepts_data_wrapped_market_response_and_normalizes_fields(): void
    {
        $xt = Mockery::mock(CslTradeXClient::class);
        $xt->shouldReceive('stockPrice')->once()->with('TEST')->andReturn($this->cslFixture('quote'));

        $provider = new CslMarketDataProvider(
            $xt,
            Mockery::mock(CslStockClient::class)
        );

        $quote = $provider->quote('TEST');

        $this->assertSame('TEST', $quote['symbol']);
        $this->assertSame('Test Company', $quote['name']);
        $this->assertSame('NGX', $quote['market']);
        $this->assertSame(99.5, $quote['price']);
        $this->assertSame(98.0, $quote['previous_close']);
        $this->assertSame(98.25, $quote['open']);
        $this->assertSame(100.0, $quote['high']);
        $this->assertSame(97.5, $quote['low']);
        $this->assertSame(1.5, $quote['change']);
        $this->assertSame(1.53, $quote['change_percent']);
        $this->assertSame(99.25, $quote['bid_price']);
        $this->assertSame(250, $quote['bid_quantity']);
        $this->assertSame(99.75, $quote['offer_price']);
        $this->assertSame(180, $quote['offer_quantity']);
        $this->assertSame(4200, $quote['volume']);
        $this->assertSame(417900, $quote['value']);
        $this->assertSame('csl', $quote['provider']);
    }
}
