<?php

namespace App\Services\Stocks\Mock;

use App\Services\Stocks\Contracts\StockBroker;
use Illuminate\Support\Str;

class MockDriveWealthService implements StockBroker
{
    public function buy(array $data): array
    {
        return [
            'order_id' => 'DW_' . Str::random(8),
            'symbol' => $data['symbol'],
            'side' => 'buy',
            'quantity' => $data['quantity'],
            'price' => rand(100, 500),
            'status' => 'filled',
            'executed_at' => now()->toDateTimeString(),
        ];
    }

    public function sell(array $data): array
    {
        return [
            'order_id' => 'DW_' . Str::random(8),
            'symbol' => $data['symbol'],
            'side' => 'sell',
            'quantity' => $data['quantity'],
            'price' => rand(100, 500),
            'status' => 'filled',
            'executed_at' => now()->toDateTimeString(),
        ];
    }

    public function portfolio(int $userId): array
    {
        return [
            [
                'symbol' => 'AAPL',
                'quantity' => 12,
                'avg_price' => 175,
                'market_price' => 182,
            ],
            [
                'symbol' => 'TSLA',
                'quantity' => 4,
                'avg_price' => 230,
                'market_price' => 245,
            ],
        ];
    }

    public function history(int $userId): array
    {
        return [
            [
                'symbol' => 'AAPL',
                'side' => 'buy',
                'quantity' => 10,
                'price' => 170,
                'date' => now()->subDays(10)->toDateString(),
            ],
            [
                'symbol' => 'TSLA',
                'side' => 'buy',
                'quantity' => 3,
                'price' => 225,
                'date' => now()->subDays(6)->toDateString(),
            ],
        ];
    }
}
