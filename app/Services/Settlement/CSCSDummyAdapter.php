<?php

namespace App\Services\Settlement;

use App\Models\Trade;
use App\Models\Wallet;
use App\Models\Holding;

class CSCSDummyAdapter
{
    public function settle(Trade $trade)
    {
        // 1. Update the Trade Record
        $trade->update([
            'settlement_status' => 'settled',
            'settlement_date' => now(),
        ]);

        // 2. Logic: Move the "Assets" and "Cash"
        $user = $trade->user;
        
        if ($trade->side === 'buy') {
            // Add shares to their holdings
            Holding::updateOrCreate(
                ['user_id' => $user->id, 'symbol' => $trade->symbol],
                ['quantity' => \DB::raw("quantity + {$trade->quantity}")]
            );
        } else {
            // For 'sell', subtract shares from holdings
            Holding::where('user_id', $user->id)
                   ->where('symbol' => $trade->symbol)
                   ->decrement('quantity', $trade->quantity);
        }

        return [
            'status' => 'settled',
            'mode' => 'dummy',
            'settlement_date' => now()->toDateString(),
            'message' => 'Portfolio updated successfully'
        ];
    }
}