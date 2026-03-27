<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Trade;

class PortfolioService
{
    public function postTrade(Trade $trade)
    {
        $portfolio = Portfolio::firstOrNew([
            'user_id' => $trade->order->user_id,
            'symbol' => $trade->order->symbol,
            'market' => $trade->order->market,
        ]);

        $portfolio->quantity = ($portfolio->quantity ?? 0) + $trade->quantity;
        $portfolio->avg_price = $trade->price;
        $portfolio->save();
    }
}
