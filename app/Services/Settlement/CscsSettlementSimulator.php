<?php

namespace App\Services\Settlement;

use App\Models\Trade;
use App\Models\Settlement;
use App\Services\Portfolio\PortfolioService;
use Carbon\Carbon;
use DB;

class CscsSettlementSimulator
{
    public function settle(Trade $trade)
    {
        if (
			config('services.cscs.mode') === 'dummy' &&
			rand(1, 10) === 5
		) {
			throw new \Exception('CSCS settlement failed');
		}

		DB::transaction(function () use ($trade) {

            // Simulate delay (T+2)
            $settlementDate = Carbon::now()->addDays(2);

            $settlement = Settlement::create([
                'trade_id' => $trade->id,
                'user_id' => $trade->order->user_id,
                'quantity' => $trade->quantity,
                'price' => $trade->price,
                'settlement_date' => $settlementDate,
                'status' => 'settled'
            ]);

            // Post to portfolio
            app(PortfolioService::class)->postTrade($trade);

            return $settlement;
        });
    }
}
