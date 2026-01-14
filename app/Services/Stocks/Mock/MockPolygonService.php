<?php

namespace App\Services\Stocks\Mock;

use App\Services\Stocks\Contracts\MarketDataProvider;
use Carbon\Carbon;

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

    public function historical(string $symbol, string $range = '7d', ?string $start = null, ?string $end = null, ?string $view = null): array
    {
        $points = [];

        if (strtolower($range) === 'custom' && $start && $end) {
            // Generate daily data for custom range
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $days = $startDate->diffInDays($endDate) + 1;

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $startDate->copy()->addDays($i)->toDateString();
                $points[] = [
                    'date' => $date,
                    'open' => rand(100, 400),
                    'close' => rand(100, 400),
                    'high' => rand(400, 500),
                    'low' => rand(90, 100),
                ];
            }
        } elseif (strtolower($range) === '1d') {
            if ($view === 'minute') {
                // Generate minute data for 1D minute view (24 minutes)
                for ($i = 23; $i >= 0; $i--) {
                    $points[] = [
                        'date' => now()->subMinutes($i)->toISOString(),
                        'open' => rand(100, 400),
                        'close' => rand(100, 400),
                        'high' => rand(400, 500),
                        'low' => rand(90, 100),
                    ];
                }
            } else {
                // Generate hourly data for 1D view (24 hours)
                for ($i = 23; $i >= 0; $i--) {
                    $points[] = [
                        'date' => now()->subHours($i)->toISOString(),
                        'open' => rand(100, 400),
                        'close' => rand(100, 400),
                        'high' => rand(400, 500),
                        'low' => rand(90, 100),
                    ];
                }
            }
        } else {
            // Generate data based on range: daily, monthly, or yearly
            $config = match (strtolower($range)) {
                '1w', '7d' => ['periods' => 7, 'interval' => 'day'],
                '1m', '30d' => ['periods' => 30, 'interval' => 'day'],
                '90d' => ['periods' => 3, 'interval' => 'month'],
                '1y' => ['periods' => 12, 'interval' => 'month'],
                'all' => ['periods' => 24, 'interval' => 'month'],
                default => ['periods' => 7, 'interval' => 'day'],
            };

            $periods = $config['periods'];
            $interval = $config['interval'];

            for ($i = $periods - 1; $i >= 0; $i--) {
                $date = match ($interval) {
                    'month' => now()->subMonths($i)->toDateString(),
                    'year' => now()->subYears($i)->toDateString(),
                    default => now()->subDays($i)->toDateString(),
                };

                $points[] = [
                    'date' => $date,
                    'open' => rand(100, 400),
                    'close' => rand(100, 400),
                    'high' => rand(400, 500),
                    'low' => rand(90, 100),
                ];
            }
        }

        return [
            'symbol' => strtoupper($symbol),
            'range' => $range,
            'data' => $points,
        ];
    }
}
