<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketData\IndicatorService;
use App\Services\MarketData\StockMarketService;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function candles(Request $request)
    {
        $symbol = $request->get('symbol', 'AAPL');
        $interval = $request->get('interval', '1');

        $data = cache()->remember("candles_{$symbol}_{$interval}", 30, function () use ($symbol, $interval) {
            $market = new StockMarketService;
            $indicator = new IndicatorService;

            $candles = $market->candles($symbol, $interval);

            return [
                'candles' => $candles,
                'ma14' => $indicator->movingAverage($candles, 14),
            ];
        });

        return response()->json($data);
    }

    public function stockHistory(Request $request, $symbol)
    {
        $interval = $request->get('interval', '1D');
        $market = new StockMarketService;

        return response()->json([
            'symbol' => $symbol,
            'history' => $market->candles($symbol, $interval),
        ]);
    }
}
