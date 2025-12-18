<?php

namespace App\Services\ContractNotes;

use App\Models\Order;

class ContractNoteService
{
    public function generate(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'client' => $order->user->name,
            'symbol' => $order->symbol,
            'quantity' => $order->filled_quantity,
            'price' => $order->price,
            'trade_date' => now()->toDateString(),
            'settlement_date' => now()->addDays(2)->toDateString(),
        ];
    }
}
