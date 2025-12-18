<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio;
use App\Models\Trade;

class PortfolioService
{
    public function postTrade(Trade $trade)
    {
        Portfolio::updateOrCreate(
            [
                'user_id' => $trade->order->user_id,
                'symbol' => $trade->order->symbol,
                'market' => $trade->order->market,
            ],
            [
                'quantity' => \DB::raw('quantity + '.$trade->quantity),
                'avg_price' => $trade->price,
            ]
        );
    }
}
