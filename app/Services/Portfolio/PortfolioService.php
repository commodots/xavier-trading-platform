<?php

namespace App\Services\Portfolio;

use App\Models\Order;
use App\Models\Portfolio;

class PortfolioService
{
    public function post(Order $order): void
    {
        Portfolio::updateOrCreate(
            [
                'user_id' => $order->user_id,
                'symbol' => $order->symbol,
            ],
            [
                'units' => $order->side === 'buy'
                    ? DB::raw("units + {$order->filled_quantity}")
                    : DB::raw("units - {$order->filled_quantity}")
            ]
        );
    }
	public function postTrade(Trade $trade): void
    {
        $order = $trade->order;

        if ($order->side === 'sell') {
            $this->decreaseHolding($order->user_id, $order->symbol, $trade->quantity);
            return;
        }

        // BUY trade
        Portfolio::updateOrCreate(
            [
                'user_id' => $order->user_id,
                'symbol' => $order->symbol,
            ],
            $this->calculateNewHolding($order->user_id, $order->symbol, $trade)
        );
    }

    protected function calculateNewHolding($userId, $symbol, Trade $trade): array
    {
        $portfolio = Portfolio::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->first();

        if (!$portfolio) {
            return [
                'quantity' => $trade->quantity,
                'avg_price' => $trade->price,
            ];
        }

        $totalQty = $portfolio->quantity + $trade->quantity;
        $totalValue =
            ($portfolio->quantity * $portfolio->avg_price) +
            ($trade->quantity * $trade->price);

        return [
            'quantity' => $totalQty,
            'avg_price' => $totalValue / $totalQty,
        ];
    }

    protected function decreaseHolding($userId, $symbol, $qty): void
    {
        $portfolio = Portfolio::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->firstOrFail();

        $portfolio->decrement('quantity', $qty);
    }
}
