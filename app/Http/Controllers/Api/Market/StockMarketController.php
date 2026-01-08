<?php

namespace App\Http\Controllers\Api\Market;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class StockMarketController extends Controller
{
    public function history(Request $request, string $symbol)
    {
        $range = $request->get('range', '7d'); // 7d | 30d | 90d
        $market = $request->get('market', 'ngx');

        $days = match ($range) {
            '1d', '1D' => 1,
            '1w', '1W', '7d' => 7,
            '1m', '1M', '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $data = $this->generateDummyCandles($days);

        return response()->json([
            'success' => true,
            'symbol'  => strtoupper($symbol),
            'market'  => $market,
            'range'   => $range,
            'data'    => $data,
        ]);
    }

    /**
     * Generate realistic OHLCV candles
     */
    private function generateDummyCandles(int $days): array
    {
        $candles = [];
        $price = rand(100, 500); // base price

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

            $price = $close; // next candle continuity
        }

        return $candles;
    }
}
