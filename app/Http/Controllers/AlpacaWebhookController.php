<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Position;
use Illuminate\Http\Request;

class AlpacaWebhookController extends Controller
{
    public function handle(Request $request)
    {
       
        $response = response()->json(['ok'], 200);

        $event = $request->all();

        $orderId = $event['order']['id'] ?? null;

        $order = Order::where('alpaca_order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['ignored']);
        }

        $status = $event['event']; // fill, partial_fill, canceled

        if ($status === 'fill' && $order->status !== 'filled') {
            $order->update([
                'status' => 'filled',
                'price' => $event['order']['filled_avg_price'] ?? $order->price,
            ]);

            $this->syncPosition($order);
        }

        return response()->json(['ok']);
    }

    protected function syncPosition($order)
    {
        $position = Position::firstOrNew([
            'user_id' => $order->user_id,
            'symbol' => $order->symbol,
        ]);

        if ($order->side === 'buy') {
            $position->qty += $order->quantity;
        } else {
            $position->qty -= $order->quantity;
        }

        $position->avg_price = $order->price;
        $position->save();
    }
}
