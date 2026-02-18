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

            foreach ($trades as $trade) {
                $this->processTradeSettlement($trade, $order);
            }
        });
    }

    private function processTradeSettlement(Trade $trade, Order $order): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $currency = $order->currency;
        
        $wallet = Wallet::where('user_id', $order->user_id)->where('currency', $currency)->firstOrFail();
        $portfolio = Portfolio::where('user_id', $order->user_id)->where('symbol', $order->symbol)->firstOrFail();

        $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
        $unclearedCol = $currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';

        if ($order->side === 'buy') {
            // SETTLE BUY: 
            // Remove cash from LOCKED 
            $wallet->decrement('locked', $totalValue);

            //  Shares officially yours (Move from uncleared to cleared)
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->increment('cleared_quantity', $trade->quantity);

        } else { 
            // SETTLE SELL:
            // Shares officially gone (Remove from uncleared & total)
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->decrement('quantity', $trade->quantity);

            // Cash officially yours (Move from uncleared to cleared)
            $wallet->decrement($unclearedCol, $totalValue);
            $wallet->increment($clearedCol, $totalValue);
        }

        // Mark the trade as settled
        $trade->update([
            'settlement_status' => 'settled',
            'settlement_date' => Carbon::now()->toDateString(),
        ]);
        
        // Mark order as filled if all trades are done
        $pendingTradesCount = clone $order->trades()->where('settlement_status', 'pending')->count();
        if ($pendingTradesCount === 0) {
            $order->update(['status' => 'filled']);
        }
    }
}