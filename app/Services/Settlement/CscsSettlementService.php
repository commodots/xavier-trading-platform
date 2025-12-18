<?php

namespace App\Services\Settlement;

use App\Models\Trade;
use App\Models\Portfolio;
use Illuminate\Support\Facades\DB;

class CscsSettlementService
{
    public function settle(Trade $trade): void
    {
        DB::transaction(function () use ($trade) {

            // Optional error simulation
            if (config('services.cscs.simulate_errors')) {
                if (rand(1, 10) === 5) {
                    throw new \Exception('CSCS settlement failed');
                }
            }

            Portfolio::updateOrCreate(
                [
                    'user_id' => $trade->order->user_id,
                    'symbol'  => $trade->order->symbol,
                ],
                [
                    'quantity'  => DB::raw('quantity + ' . $trade->quantity),
                    'avg_price' => $trade->price,
                ]
            );

            // Mark trade as settled
            $trade->update([
                'settled_at' => now(),
            ]);
        });
    }
}
