<?php

namespace Tests\Feature\CSL;

use App\Models\Symbol;
use App\Services\CSL\CslInstrumentService;
use App\Services\CSL\CslStockClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslInstrumentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_csl_instrument_fields_are_mapped_to_symbols(): void
    {
        $client = Mockery::mock(CslStockClient::class);
        $client->shouldReceive('instruments')->once()->andReturn([
            'result' => [[
                'GetCRSTInstruments' => [[
                    'symbol_id' => '42',
                    'symbol_description' => 'Test Industries',
                    'symbol_type' => 'equity',
                    'market_description' => 'NGX',
                    'current_price' => '123.45',
                    'market_id' => 'NGX1',
                    'isin_identifier' => 'NGTEST42',
                ]],
            ]],
        ]);

        $count = (new CslInstrumentService($client))->sync();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('symbols', [
            'provider' => 'csl',
            'provider_symbol_id' => '42',
            'symbol' => '42',
            'market_id' => 'NGX1',
            'isin' => 'NGTEST42',
        ]);
        $this->assertSame('123.4500', (string) Symbol::first()->last_price);
    }
}
