<?php

namespace Tests\Feature;

use App\Services\CSL\CslStockBroker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CslStockBrokerTest extends TestCase
{
    public function test_buy_maps_xavier_order_to_csl(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),

            '*/DoCRXTBuyOrder' => Http::response([
                'status' => 'success',
                'result' => [
                    [
                        'code' => 'A',
                        'remarks' => 'Accepted',
                    ],
                ],
            ], 200),
        ]);

        $broker = app(
            CslStockBroker::class
        );

        $result = $broker->buy([
            'market_id' => 'NGX1',
            'market_account_id' => 'TEST001',
            'symbol' => 'TIP',
            'quantity' => 100,
            'type' => 'limit',
            'limit_price' => 150,
        ]);

        $this->assertSame(
            'csl',
            $result['provider']
        );

        $this->assertSame(
            'buy',
            $result['side']
        );

        $this->assertSame(
            'success',
            $result['status']
        );
    }
}
