<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Trade;
use App\Models\Wallet;
use App\Models\Portfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SettlementService
{
    /**
     * Alias for settleOrder
     */
    public function settle(Order $order): void
    {
        $this->settleOrder($order);
    }

    /**
     * Entry point: Settles all unsettled trades for an order.
     */
    public function settleOrder(Order $order): void
    {
        Log::info("SettlementService: settleOrder called for order {$order->id}, side {$order->side}");

        DB::transaction(function () use ($order) {
            // Only fetch trades that haven't been processed yet
            $trades = $order->trades()->where('settlement_status', 'pending')->get();

            Log::info("SettlementService: Found {$trades->count()} pending trades for order {$order->id}");

            foreach ($trades as $trade) {
                $this->processTradeEntry($trade, $order);
            }
        });

        Log::info("SettlementService: settleOrder completed for order {$order->id}");
    }

    /**
     * Phase 1: Trade Entry (T+0)
     * Moves funds/assets to "Uncleared" or "Locked" status.
     */
    private function processTradeEntry(Trade $trade, Order $order): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $settlementDate = $this->calculateSettlementDate(Carbon::now(), 2);

        if ($order->side === 'buy') {
            $this->handleBuyEntry($order->user_id, $order->currency, $order->symbol, $trade);
        } else {
            $this->handleSellEntry($order->user_id, $order->currency, $order->symbol, $trade);
        }

        $trade->update([
            'settlement_status' => 'pending',
            'settlement_date' => $settlementDate,
        ]);
    }

    private function handleBuyEntry($userId, $currency, $symbol, Trade $trade): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $order = $trade->order;
        $wallet = Wallet::where('user_id', $userId)->where('currency', $currency)->firstOrFail();

        // 1. Move Cash to Locked (use currency-specific cleared balance)
        if ($currency === 'NGN') {
            $wallet->decrement('ngn_cleared', $totalValue);
        } elseif ($currency === 'USD') {
            $wallet->decrement('usd_cleared', $totalValue);
        }
        $wallet->increment('locked', $totalValue);

        // 2. Update Portfolio with Uncleared Quantity
        $portfolio = Portfolio::firstOrCreate(
            ['user_id' => $userId, 'symbol' => $symbol],
            [
                'name' => $symbol,
                'category' => 'local',
                'currency' => $order->currency,
                'market_price' => $trade->price,
                'quantity' => 0,
                'avg_price' => 0,
                'cleared_quantity' => 0,
                'uncleared_quantity' => 0
            ]
        );

        // Update Weighted Average Price
        $currentCost = $portfolio->quantity * $portfolio->avg_price;
        $newTotalQty = $portfolio->quantity + $trade->quantity;
        $newAvgPrice = ($currentCost + $totalValue) / $newTotalQty;

        $portfolio->update([
            'avg_price' => $newAvgPrice,
            'quantity' => $newTotalQty,
            'uncleared_quantity' => $portfolio->uncleared_quantity + $trade->quantity
        ]);
    }

    private function handleSellEntry($userId, $currency, $symbol, Trade $trade): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $portfolio = Portfolio::where('user_id', $userId)->where('symbol', $symbol)->firstOrFail();

        // 1. Move Assets to Uncleared (Locked for sale)
        $portfolio->decrement('cleared_quantity', $trade->quantity);
        $portfolio->increment('uncleared_quantity', $trade->quantity);

        // 2. Wallet record (Profit is uncleared until T+2) - use currency-specific column
        $wallet = Wallet::where('user_id', $userId)->where('currency', $currency)->firstOrFail();
        if ($currency === 'NGN') {
            $wallet->increment('ngn_uncleared', $totalValue);
        } elseif ($currency === 'USD') {
            $wallet->increment('usd_uncleared', $totalValue);
        }
    }

    private function calculateSettlementDate(Carbon $date, int $days): string
    {
        $count = 0;
        while ($count < $days) {
            $date->addDay();
            if ($date->isWeekday()) $count++;
        }
        return $date->toDateString();
    }
}