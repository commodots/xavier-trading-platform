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
   
    public function settle(Order $order): void
    {
        $this->settleOrder($order);
    }

    /**
     * Settles all unsettled trades for an order safely inside a monitored transaction container.
     */
    public function settleOrder(Order $order): void
    {
        Log::info("SettlementService: settleOrder initiated for order {$order->id}, side {$order->side}");

        // Fail-safe check: If the order was structurally killed, reject the settlement loop
        if (in_array($order->status, ['cancelled', 'failed'])) {
            Log::warning("SettlementService: Aborted settlement for order {$order->id} because status is {$order->status}");
            return;
        }

        DB::transaction(function () use ($order) {
            $trades = $order->trades()->where('settlement_status', 'pending')->get();

            foreach ($trades as $trade) {
                try {
                    $this->processTradeSettlement($trade, $order);
                    Log::info("SettlementService: Trade {$trade->id} settled successfully for order {$order->id}");
                } catch (\Exception $e) {
                    Log::error("SettlementService: Critical failure settling trade {$trade->id}: " . $e->getMessage());
                    throw $e; // Rollback entire transaction sequence if a single step errors out
                }
            }
        });
    }

    /**
     * Executes double-entry balance shifting for individual trade executions.
     */
    public function processTradeSettlement(Trade $trade, Order $order): void
    {
        $totalValue = $trade->quantity * $trade->price;
        $currency = $order->currency;

        $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
        $unclearedCol = $currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';

        // Fetch Wallets and Portfolios using strict Pessimistic Locking (lockForUpdate)
        $wallet = Wallet::where('user_id', $order->user_id)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            throw new \Exception("Wallet missing for user {$order->user_id} [{$currency}]");
        }

        // FirstOrCreate handles users buying an asset for the first time
        $portfolio = Portfolio::firstOrCreate(
            ['user_id' => $order->user_id, 'symbol' => $order->symbol],
            ['quantity' => 0, 'cleared_quantity' => 0, 'uncleared_quantity' => 0]
        );
        
        // Lock portfolio record for modification safety
        $portfolio = Portfolio::where('id', $portfolio->id)->lockForUpdate()->first();

        // Process Ledger Operations
        if ($order->side === 'buy') {
            // SETTLE BUY:
            // Deduct from the Uncleared Cash balance bucket (locked cash becomes spent cash)
            $toDeduct = min($wallet->{$unclearedCol}, $totalValue);
            if ($toDeduct > 0) {
                $wallet->decrement($unclearedCol, $toDeduct);
            }

            $wallet->decrement('balance', $totalValue);
            $wallet->decrement('locked', $totalValue);

            // Move asset tokens out of holding status directly into clear balances
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->increment('cleared_quantity', $trade->quantity);
            $portfolio->increment('quantity', $trade->quantity);
            
        } else {
            // SETTLE SELL:
            // Protect against negative balances from double-settlement calls
            $toSettle = min($wallet->{$unclearedCol}, $totalValue);

            // Deduct shares out of the user's portfolio holding track entirely
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->decrement('quantity', $trade->quantity);

            // Shift dynamic cash components safely out of holding reserves into settled access balances
            if ($toSettle > 0) {
                $wallet->decrement($unclearedCol, $toSettle);
                $wallet->increment($clearedCol, $toSettle);
            }
        }

        // Persist local instances
        $wallet->save();
        $portfolio->save();

        //  Mark the trade records as officially settled
        $trade->update([
            'settlement_status' => 'settled',
            'is_settled'        => true,
            'settlement_date'   => Carbon::now()->toDateString(),
        ]);

        // Update parent order state if all child trades have been cleared out
        if ($order->trades()->where('settlement_status', 'pending')->count() === 0) {
            $order->update(['status' => 'filled']);
        }
    }

    /**
     * Automated job runner scanning for T+1 settlement horizons.
     */
    public function processDailySettlements(): void
    {
        // Fetch pending items that have cleared their 24-hour maturation limit
        $pendingTrades = Trade::where('settlement_status', 'pending')
            ->where('created_at', '<=', now()->subDay())
            ->get();

        foreach ($pendingTrades as $trade) {
            $order = $trade->order;
            if (!$order || in_array($order->status, ['cancelled', 'failed'])) {
                continue; 
            }

            Log::info("Executing automated T+1 daily settlement routine for trade record: {$trade->id}");

            try {
                // Keep individual order processes separate so one broken trade record doesn't stall the whole loop
                $this->settleOrder($order);
            } catch (\Exception $e) {
                Log::error("Automated settlement processing failed for trade {$trade->id}: " . $e->getMessage());
            }
        }
    }
}