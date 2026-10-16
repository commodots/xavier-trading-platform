<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketData\IndicatorService;
use App\Services\MarketData\StockMarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function __construct(
        private StockMarketService $stockMarket,
        private IndicatorService $indicator
    ) {}

    public function candles(Request $request): JsonResponse
    {
        $symbol = $request->get('symbol', 'AAPL');
        $interval = $request->get('interval', '1');
        $market = $request->string('market', 'GLOBAL')->upper()->toString();

        $data = cache()->remember(
            "candles_{$market}_{$symbol}_{$interval}",
            30,
            function () use ($symbol, $interval, $market) {
                $candles = $this->stockMarket->candles(
                    $symbol,
                    $interval,
                    market: $market
                );

                return [
                    'candles' => $candles,
                    'ma14' => $this->indicator->movingAverage($candles, 14),
                    'is_demo' => filter_var(
                        config('services.alpaca.mock', true),
                        FILTER_VALIDATE_BOOL
                    ),
                ];
            }
        );

        return response()->json($data);
    }

    public function stockHistory(Request $request, string $symbol): JsonResponse
    {
        $interval = $request->get('interval', '1D');

        return response()->json([
            'symbol' => $symbol,
            'history' => $this->stockMarket->candles(
                $symbol,
                $interval,
                market: $request->string('market', 'GLOBAL')->upper()->toString()
            ),
        ]);
    }
}
