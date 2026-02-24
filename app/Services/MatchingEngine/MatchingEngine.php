<?php

namespace App\Services\MatchingEngine;

use App\Models\Order;
use App\Models\Trade;
use App\Services\PriceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Settlement\CSCSSettlementSimulator;

class MatchingEngine
{
    /**
     * Process an incoming order and attempt to match it against the book
     * or simulate a market fill if in dummy mode.
     */
    public function process(Order $incomingOrder): void
    {
        // Check for simulated outages
        if ($this->shouldFail()) {
            throw new \Exception('Simulated NGX gateway outage');
        }

        DB::transaction(function () use ($incomingOrder) {
            // Lock the incoming order for processing
            $incomingOrder->lockForUpdate();

            // For Dummy NGX: We simulate an immediate "Market Fill" 
            // at the current price from our PriceService.
            if (config('services.ngx.mode') === 'dummy') {
                $this->executeDummyMatch($incomingOrder);
                return;
            }

            // For Live/Real matching: We match against other users' orders
            $this->executeOrderBookMatch($incomingOrder);
        });
    }

    /**
     * Simulates an immediate fill at market price (Used for Model Portfolios)
     */
    protected function executeDummyMatch(Order $incoming): void
    {
        $marketPrice = app(PriceService::class)->getCurrentPrice($incoming->symbol, 'local');
        $qtyToFill = $incoming->quantity - $incoming->filled_quantity;

        if ($qtyToFill <= 0) return;

        // Calculate T+2 date using business days logic
        $settlementDate = now()->addDays(2); 

        Trade::create([
            'order_id' => $incoming->id,
            'price' => $marketPrice,
            'quantity' => $qtyToFill,
            'settlement_status' => 'pending', 
            'settlement_date' => $settlementDate->toDateString(),
        ]);

        // Add the filled quantity
        $incoming->increment('filled_quantity', $qtyToFill);
        
        
        $this->syncStatus($incoming); 
    }

    /**
     * Standard Peer-to-Peer Matching (Used for real trading between users)
     */
    protected function executeOrderBookMatch(Order $incoming): void
    {
        // Find opposite orders (Buy vs Sell)
        $matches = Order::where('symbol', $incoming->symbol)
            ->where('market', $incoming->market)
            ->where('side', $incoming->side === 'buy' ? 'sell' : 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('id', '!=', $incoming->id)
            ->orderBy('price', $incoming->side === 'buy' ? 'asc' : 'desc')
            ->lockForUpdate()
            ->get();

        foreach ($matches as $counter) {
            $remaining = $incoming->quantity - $incoming->filled_quantity;
            if ($remaining <= 0) break;

            if (!$this->priceMatch($incoming, $counter)) continue;

            $qty = min($remaining, $counter->quantity - $counter->filled_quantity);

            $trade = Trade::create([
                'order_id' => $incoming->id,
                'counterparty_order_id' => $counter->id,
                'price' => $counter->price,
                'quantity' => $qty,
            ]);
            
            app(\App\Services\ContractNote\ContractNoteService::class)->generate($trade);
            app(CSCSSettlementSimulator::class)->settleTrade($trade);

            $incoming->increment('filled_quantity', $qty);
            $counter->increment('filled_quantity', $qty);

            $this->syncStatus($incoming);
            $this->syncStatus($counter);

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

    protected function syncStatus(Order $order): void
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