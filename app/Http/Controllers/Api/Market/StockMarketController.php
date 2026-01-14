<?php

namespace App\Http\Controllers\Api\Market;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class StockMarketController extends Controller
{
    public function history(Request $request, string $symbol)
    {
        $range = $request->get('range', '7d');
        $market = $request->get('market', 'ngx');

        // Check if we need hourly data for the 1D view
        if (strtolower($range) === '1d') {
            $data = $this->generateHourlyCandles(24);
        } else {
            $days = match (strtolower($range)) {
                '1w', '7d' => 7,
                '1m', '30d' => 30,
                '90d' => 90,
                '1y' => 365,
                'all' => 730,
                default => 7,
            };
            $data = $this->generateDummyCandles($days);
        }

        return response()->json([
            'success' => true,
            'symbol'  => strtoupper($symbol),
            'market'  => $market,
            'range'   => $range,
            'data'    => [
                'data' => $data // Wrapped in 'data' to match your Vue frontend expectation
            ],
        ]);
    }

    /**
     * Generate hourly points for the 1D chart
     */
    private function generateHourlyCandles(int $hours): array
    {
        $candles = [];
        $price = rand(100, 500);

        for ($i = $hours - 1; $i >= 0; $i--) {
            // Using 'c' format (ISO 8601) includes the Time so the chart can read it
            $date = Carbon::now()->subHours($i)->format('Y-m-d H:i:s');

            $open = $price;
            $high = $open + rand(2, 10);
            $low  = $open - rand(2, 10);
            $close = rand($low, $high);
            $volume = rand(1000, 5000);

            $candles[] = [
                'date'   => $date,
                'open'   => round($open, 2),
                'high'   => round($high, 2),
                'low'    => round($low, 2),
                'close'  => round($close, 2),
                'volume' => $volume,
            ];
            $price = $close;
        }
        return $candles;
    }

    /**
     * Generate daily OHLCV candles
     */
    private function generateDummyCandles(int $days): array
    {
        $candles = [];
        $price = rand(100, 500);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');

            $open = $price;
            $high = $open + rand(5, 25);
            $low  = $open - rand(5, 20);
            $close = rand($low, $high);
            $volume = rand(10_000, 250_000);

            $candles[] = [
                'date'   => $date,
                'open'   => round($open, 2),
                'high'   => round($high, 2),
                'low'    => round($low, 2),
                'close'  => round($close, 2),
                'volume' => $volume,
            ];

            $price = $close;
        }
        return $candles;
    }
}