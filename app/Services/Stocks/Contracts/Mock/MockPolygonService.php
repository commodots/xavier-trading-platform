<?php

namespace App\Services\Stocks\Contracts\Mock;

use App\Services\Stocks\Contracts\MarketDataProvider;

class MockPolygonService implements MarketDataProvider
{
    public function quote(string $symbol): array
    {
        return [
            'symbol' => strtoupper($symbol),
            'price' => rand(100, 500),
            'change' => round(rand(-50, 50) / 10, 2),
            'volume' => rand(1_000_000, 5_000_000),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    public function historical(string $symbol, string $range = '7d'): array
    {
        $points = [];

        for ($i = 6; $i >= 0; $i--) {
            $points[] = [
                'date' => now()->subDays($i)->toDateString(),
                'open' => rand(100, 400),
                'close' => rand(100, 400),
                'high' => rand(400, 500),
                'low' => rand(90, 100),
            ];
        }

        return [
            'symbol' => strtoupper($symbol),
            'range' => $range,
            'data' => $points,
        ];
    }
}
