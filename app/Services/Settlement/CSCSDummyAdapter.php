<?php

namespace App\Services\Settlement;

use App\Models\Order;

class CSCSDummyAdapter
{
    public function settle(Order $order): void
    {
        $order->update([
            'settlement_status' => 'settled',
            'settled_at' => now(),
        ]);
    }
}
