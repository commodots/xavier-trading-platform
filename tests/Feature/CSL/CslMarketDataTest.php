<?php

namespace Tests\Feature\CSL;

use App\Services\CSL\CslMarketDataProvider;
use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use Mockery;
use Tests\TestCase;

class CslMarketDataTest extends TestCase
{
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
}
