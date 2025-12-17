<?php

namespace App\Services\MatchingEngine;

use App\Models\Order;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;

class MatchingEngine
{
    public function match(Order $incomingOrder)
    {
        DB::transaction(function () use ($incomingOrder) {

            $oppositeSide = $incomingOrder->side === 'buy' ? 'sell' : 'buy';

            $query = Order::where('symbol', $incomingOrder->symbol)
                ->where('side', $oppositeSide)
                ->where('status', 'open')
                ->where('price', $incomingOrder->side === 'buy'
                    ? '<=' : '>='
                    , $incomingOrder->price)
                ->orderBy('price', $incomingOrder->side === 'buy' ? 'asc' : 'desc')
                ->orderBy('created_at');

            $matches = $query->get();

            foreach ($matches as $match) {
                if ($incomingOrder->filled_quantity >= $incomingOrder->quantity) {
                    break;
                }

                $remainingIncoming = $incomingOrder->quantity - $incomingOrder->filled_quantity;
                $remainingMatch = $match->quantity - $match->filled_quantity;

                $fillQty = min($remainingIncoming, $remainingMatch);

                Trade::create([
                    'order_id' => $incomingOrder->id,
                    'counterparty_order_id' => $match->id,
                    'price' => $match->price,
                    'quantity' => $fillQty,
                    'fee' => 0,
                ]);

                $incomingOrder->filled_quantity += $fillQty;
                $match->filled_quantity += $fillQty;

                $incomingOrder->status =
                    $incomingOrder->filled_quantity == $incomingOrder->quantity
                        ? 'filled' : 'partially_filled';

                $match->status =
                    $match->filled_quantity == $match->quantity
                        ? 'filled' : 'partially_filled';

                $incomingOrder->save();
                $match->save();
            }
        });
    }
}
