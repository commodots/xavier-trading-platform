<?php

namespace App\Services\Portfolio;

use App\Models\Trade;
use App\Models\Portfolio;

class PortfolioService
{
    public static function postTrade(Trade $trade)
    {
        Portfolio::updateOrCreate(
            [
                'user_id' => $trade->order->user_id,
                'symbol' => $trade->order->symbol,
            ],
            [
                'quantity' => \DB::raw('quantity + ' . $trade->quantity),
            ]
        );
    }
}
