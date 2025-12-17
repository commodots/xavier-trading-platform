<?php

namespace App\Services\Contracts;

use App\Models\Trade;

class ContractNoteService
{
    public static function generate(Trade $trade)
    {
        return [
            'trade_id' => $trade->id,
            'symbol' => $trade->order->symbol,
            'price' => $trade->price,
            'quantity' => $trade->quantity,
            'total' => $trade->price * $trade->quantity,
            'date' => now()->toDateString(),
        ];
    }
}
