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
            $trades = $order->trades()->where('settlement_status', 'pending')->get();

            foreach ($trades as $trade) {
                try {
                    $this->processTradeSettlement($trade, $order);
                    Log::info("SettlementService: Trade {$trade->id} settled successfully for order {$order->id}");
                } catch (\Exception $e) {
                    Log::error("SettlementService: Error settling trade {$trade->id} for order {$order->id}: " . $e->getMessage());
                    throw $e;
                }
            }
        });
    }

    private function processTradeSettlement(Trade $trade, Order $order): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $currency = $order->currency;
        try {
            $wallet = Wallet::where('user_id', $order->user_id)->where('currency', $currency)->first();
            if (!$wallet) {
                throw new \Exception("Wallet not found for user {$order->user_id} and currency {$currency}");
            }
            $portfolio = Portfolio::where('user_id', $order->user_id)->where('symbol', $order->symbol)->first();
            if (!$portfolio) {
                throw new \Exception("Portfolio not found for user {$order->user_id} and symbol {$order->symbol}");
            }
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
        } catch (\Exception $e) {
            Log::error("SettlementService: Error processing settlement for trade {$trade->id}, order {$order->id}: " . $e->getMessage());
            throw $e;
        }
        // Mark the trade as settled
        $trade->update([
            'settlement_status' => 'settled',
            'settlement_date' => Carbon::now()->toDateString(),
        ]);

        // Mark order as filled if all trades are done
        if ($order->trades()->where('settlement_status', 'pending')->count() === 0) {
            $order->update(['status' => 'filled']);
        }
    }
}

