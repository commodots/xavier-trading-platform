<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketData\StockMarketService;
use App\Services\MarketData\IndicatorService;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function candles(Request $request)
    {
        $symbol = $request->get('symbol', 'AAPL');
        $interval = $request->get('interval', '1D');

        $market = new StockMarketService();
        $indicator = new IndicatorService();

        $candles = $market->candles($symbol, $interval);

        return response()->json([
            'candles' => $candles,
            'ma14' => $indicator->movingAverage($candles, 14),
        ]);
    }

    public function stockHistory(Request $request, $symbol)
    {
        $interval = $request->get('interval', '1D');
        $market = new StockMarketService();

        return response()->json([
            'symbol' => $symbol,
            'history' => $market->candles($symbol, $interval),
        ]);
    }
}
