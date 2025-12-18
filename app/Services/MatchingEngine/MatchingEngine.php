<?php

namespace App\Services\MatchingEngine;

use App\Models\Order;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Settlement\CSCSSettlementSimulator;

class MatchingEngine
{
    public function process(Order $incomingOrder): void
    {
        // 🔴 Error Simulation
        if (
			config('services.ngx.mode') === 'dummy' &&
			config('services.ngx.simulate_errors')
		) {
			throw new \Exception('Simulated NGX outage');
		}

        DB::transaction(function () use ($incomingOrder) {

            $book = new OrderBook();

            $orders = Order::where('symbol', $incomingOrder->symbol)
                ->where('market', $incomingOrder->market)
                ->whereIn('status', ['open', 'partially_filled'])
                ->where('id', '!=', $incomingOrder->id)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $book->add($order);
            }

            $this->match($incomingOrder, $book);
        });
    }

    protected function match(Order $incoming, OrderBook $book): void
    {
        $matches = $incoming->side === 'buy'
            ? $book->sellOrders
            : $book->buyOrders;

        foreach ($matches as $counter) {

            if ($incoming->filled_quantity >= $incoming->quantity) {
                break;
            }

            if (!$this->priceMatch($incoming, $counter)) {
                continue;
            }

            $qty = min(
                $incoming->quantity - $incoming->filled_quantity,
                $counter->quantity - $counter->filled_quantity
            );

            Trade::create([
                'order_id' => $incoming->id,
                'counterparty_order_id' => $counter->id,
                'price' => $counter->price,
                'quantity' => $qty,
            ]);
			
			app(\App\Services\ContractNoteService::class)->generate($trade);

			app(CSCSSettlementSimulator::class)->settleTrade($trade);

            $incoming->increment('filled_quantity', $qty);
            $counter->increment('filled_quantity', $qty);

            $this->updateStatus($incoming);
            $this->updateStatus($counter);

            // 🔔 Audit
            Log::info('Order matched', [
                'buy_or_sell' => $incoming->side,
                'incoming' => $incoming->id,
                'counter' => $counter->id,
                'qty' => $qty
            ]);
        }
    }

    protected function priceMatch(Order $a, Order $b): bool
    {
        return $a->side === 'buy'
            ? $a->price >= $b->price
            : $a->price <= $b->price;
    }

    protected function updateStatus(Order $order): void
    {
        if ($order->filled_quantity >= $order->quantity) {
            $order->update(['status' => 'filled']);
        } elseif ($order->filled_quantity > 0) {
            $order->update(['status' => 'partially_filled']);
        }
    }

    protected function shouldFail(): bool
    {
        return config('services.ngx.mode') === 'dummy'
            && config('services.ngx.simulate_errors') === true;
    }
}
