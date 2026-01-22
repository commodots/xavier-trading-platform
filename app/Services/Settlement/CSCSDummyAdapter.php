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
                // Use the data from the order to populate the portfolio if it's new
                Portfolio::updateOrCreate(
                    ['user_id' => $user->id, 'symbol' => $order->symbol],
                    [
                        'name' => $order->name ?? $order->symbol, 
                        'category' => $order->category ?? 'local',
                        'quantity' => DB::raw("quantity + {$order->quantity}"),
                        'avg_price' => $order->price, // Simplified: Real logic would involve weighted average
                        'currency' => $order->currency ?? 'NGN',
                    ]
                );
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
                'message' => 'Portfolio updated successfully'
            ];
        });
    }
}