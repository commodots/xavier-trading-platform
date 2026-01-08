<?php

namespace App\Services\MarketData;

use Carbon\Carbon;

class StockMarketService
{
    public function candles(string $symbol, string $interval = '1D', int $limit = 100)
    {
        // MOCK DATA (later replaced with Polygon / DriveWealth)
        $data = [];
        $time = now()->subDays($limit);

        for ($i = 0; $i < $limit; $i++) {
            $open = rand(100, 120);
            $close = rand(100, 120);
            $high = max($open, $close) + rand(1, 5);
            $low = min($open, $close) - rand(1, 5);
            $volume = rand(10000, 50000);

            $data[] = [
                'time' => $time->timestamp * 1000,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
            ];

            $time->addDay();
        }

        return $data;
    }
}
