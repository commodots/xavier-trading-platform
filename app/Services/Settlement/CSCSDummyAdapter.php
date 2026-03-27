<?php

namespace App\Services\Settlement;

use App\Models\Order;
use App\Models\Portfolio;
use Illuminate\Support\Facades\DB;

class CSCSDummyAdapter
{
    public function settle(Order $order)
    {
        return DB::transaction(function () use ($order) {
            // 1. Update the order Record
            $order->update([
                'settlement_status' => 'settled',
                'settlement_date' => now(),
            ]);

            $user = $order->user;

            if ($order->side === 'buy') {
                $portfolio = Portfolio::firstOrNew([
                    'user_id' => $user->id,
                    'symbol' => $order->symbol,
                ]);

                $portfolio->name = $order->name ?? $order->symbol;
                $portfolio->category = $order->category ?? 'local';
                $portfolio->currency = $order->currency ?? 'NGN';
                $portfolio->avg_price = $order->price;
                $portfolio->quantity = ($portfolio->quantity ?? 0) + $order->quantity;

                $portfolio->save();
            } else {
                // For 'sell', decrement
                Portfolio::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->decrement('quantity', $order->quantity);
            }

            return [
                'status' => 'settled',
                'mode' => 'dummy',
                'settlement_date' => now()->toDateString(),
                'message' => 'Portfolio updated successfully',
            ];
        });
    }
}
