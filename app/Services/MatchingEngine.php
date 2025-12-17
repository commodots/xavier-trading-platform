<?php 
// app/Services/MatchingEngine.php
class MatchingEngine
{
    public function match(Order $incoming)
    {
        $opposite = Order::where('symbol', $incoming->symbol)
            ->where('side', $incoming->side === 'buy' ? 'sell' : 'buy')
            ->where('status', '!=', 'filled')
            ->orderBy('price', $incoming->side === 'buy' ? 'asc' : 'desc')
            ->get();

        foreach ($opposite as $order) {
            $qty = min(
                $incoming->quantity - $incoming->filled_qty,
                $order->quantity - $order->filled_qty
            );

            if ($qty <= 0) break;

            Trade::create([
                'buy_order_id' => $incoming->side === 'buy' ? $incoming->id : $order->id,
                'sell_order_id' => $incoming->side === 'sell' ? $incoming->id : $order->id,
                'symbol' => $incoming->symbol,
                'price' => $order->price,
                'quantity' => $qty,
            ]);

            $incoming->increment('filled_qty', $qty);
            $order->increment('filled_qty', $qty);

            $this->updateStatus($incoming);
            $this->updateStatus($order);
        }
    }

    private function updateStatus(Order $order)
    {
        if ($order->filled_qty >= $order->quantity) {
            $order->update(['status' => 'filled']);
        } else {
            $order->update(['status' => 'partial']);
        }
    }
}
