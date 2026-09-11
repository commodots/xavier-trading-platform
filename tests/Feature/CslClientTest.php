<?php

namespace Tests\Feature;

use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CslClientTest extends TestCase
{
    public function test_st_markets_endpoint_is_mapped_correctly(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),

            '*/GetCRSTMarkets' => Http::response([
                'GetCRSTMarkets' => [
                    [
                        'market_id' => 'NGX1',
                        'market_description' => 'Nigerian Exchange',
                    ],
                ],
            ], 200),
        ]);

        $client = app(CslStockClient::class);

        $result = $client->markets();

        $this->assertArrayHasKey(
            'GetCRSTMarkets',
            $result
        );

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                '/GetCRSTMarkets'
            );
        });
    }

    public function test_xt_buy_order_uses_correct_wrapper(): void
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

        $client = app(CslTradeXClient::class);

        $result = $client->buyOrder([
            'market_id' => 'NGX1',
            'market_account_id' => 'TEST001',
            'symbol_code' => 'TIP',
            'order_quantity' => 10,
            'order_type' => 'L',
            'limit_price' => 100,
            'time_in_force' => 'DAY',
        ]);

        $this->assertSame(
            'success',
            $result['status']
        );

        Http::assertSent(function ($request) {

            if (! str_contains(
                $request->url(),
                '/DoCRXTBuyOrder'
            )) {
                return false;
            }

            $body = $request->data();

            return isset($body['buyorder'])
                && $body['buyorder'][0]['market_id']
                    === 'NGX1'
                && $body['buyorder'][0]['market_account_id']
                    === 'TEST001'
                && $body['buyorder'][0]['symbol_code']
                    === 'TIP';
        });
    }
}
