<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class MarketController extends Controller
{
    public function ngx()
    {
        $data = [
            ['symbol' => 'ZENITH', 'name' => 'Zenith Bank', 'price' => rand(4000, 6000) / 100],
            ['symbol' => 'GTCO', 'name' => 'GTCO Holdings', 'price' => rand(3000, 5500) / 100],
            ['symbol' => 'ACCESS', 'name' => 'Access Bank', 'price' => rand(3000, 5500) / 100],
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function global()
    {
        $data = [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc', 'price' => rand(15000, 19000) / 100],
            ['symbol' => 'TSLA', 'name' => 'Tesla Inc', 'price' => rand(60000, 90000) / 100],
            ['symbol' => 'AMZN', 'name' => 'Amazon', 'price' => rand(10000, 15000) / 100],
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    // app/Http/Controllers/Api/MarketController.php

    public function crypto()
    {
        $prices = app(\App\Services\MarketService::class)->getPrices();
        $data = [];

        // Map the internal CoinGecko IDs to the display names/symbols you want
        $coinNames = [
            'bitcoin' => ['symbol' => 'BTC', 'name' => 'Bitcoin'],
            'ethereum' => ['symbol' => 'ETH', 'name' => 'Ethereum'],
            'tether' => ['symbol' => 'USDT', 'name' => 'Tether'],
            'binancecoin' => ['symbol' => 'BNB', 'name' => 'Binance Coin'],
            'solana' => ['symbol' => 'SOL', 'name' => 'Solana'],
            'ripple' => ['symbol' => 'XRP', 'name' => 'Ripple'],
            'cardano' => ['symbol' => 'ADA', 'name' => 'Cardano'],
            'dogecoin' => ['symbol' => 'DOGE', 'name' => 'Dogecoin'],
            'polkadot' => ['symbol' => 'DOT', 'name' => 'Polkadot'],
            'tron' => ['symbol' => 'TRX', 'name' => 'TRON'],
            'chainlink' => ['symbol' => 'LINK', 'name' => 'Chainlink'],
            'matic-network' => ['symbol' => 'MATIC', 'name' => 'Polygon'],
        ];

        foreach ($prices as $id => $val) {
            if (isset($coinNames[$id])) {
                $data[] = [
                    'symbol' => $coinNames[$id]['symbol'],
                    'name' => $coinNames[$id]['name'],
                    'price' => $val['usd'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
