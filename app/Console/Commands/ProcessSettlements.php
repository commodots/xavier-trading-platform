<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Models\Portfolio;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSettlements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settlements:process';

    /**
     * The console command description.
     */
    protected $description = 'Finalize T+2 settlements by moving uncleared assets and cash to cleared status.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();

        // Get trades that are due for settlement today or were missed in previous days
        $pendingTrades = Trade::where('settlement_status', 'pending')
            ->where('settlement_date', '<=', $today)
            ->with('order')
            ->get();

        if ($pendingTrades->isEmpty()) {
            $this->info("No pending settlements found for {$today}.");
            return;
        }

        $this->info("Processing {$pendingTrades->count()} settlements...");

        foreach ($pendingTrades as $trade) {
            try {
                DB::transaction(function () use ($trade) {
                    $order = $trade->order;
                    $totalValue = $trade->quantity * $trade->price;

                    if ($order->side === 'buy') {
                        $this->finalizeBuySettlement($order, $trade, $totalValue);
                    } elseif ($order->side === 'sell') {
                        $this->finalizeSellSettlement($order, $trade, $totalValue);
                    }

                    // Mark trade as fully settled
                    $trade->update(['settlement_status' => 'settled']);
                    
                    // Update order status only if all its trades are now settled
                    $this->checkAndCompleteOrder($order);
                });
            } catch (\Exception $e) {
                $this->error("Failed to settle Trade ID: {$trade->id}. Error: {$e->getMessage()}");
                Log::error("Settlement Error [Trade: {$trade->id}]: " . $e->getMessage());
            }
        }

        $this->info('Settlement process completed.');
    }

    private function finalizeBuySettlement($order, $trade, $totalValue)
    {
        // 1. Move Stock from Uncleared to Cleared
        $portfolio = Portfolio::where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->first();

        if ($portfolio) {
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->increment('cleared_quantity', $trade->quantity);
        }

        // 2. Finalize the Cash: Remove from Locked and Total Balance
        // Since we already deducted from 'cleared_balance' in the Service,
        // we now remove it from the system entirely.
        $wallet = Wallet::where('user_id', $order->user_id)
            ->where('currency', $order->currency)
            ->first();

        if ($wallet) {
            $wallet->decrement('locked', $totalValue);
            // Balance already deducted during entry, no need to deduct again
        }
    }

    private function finalizeSellSettlement($order, $trade, $totalValue)
    {
        // 1. Finalize Assets: Remove from total and uncleared
        $portfolio = Portfolio::where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->first();

        if ($portfolio) {
            $portfolio->decrement('uncleared_quantity', $trade->quantity);
            $portfolio->decrement('quantity', $trade->quantity);
        }

        // 2. Finalize Cash: Move from Uncleared to Cleared
        $wallet = Wallet::where('user_id', $order->user_id)
            ->where('currency', $order->currency)
            ->first();

        if ($wallet) {
            $wallet->decrement('uncleared_balance', $totalValue);
            $wallet->increment('cleared_balance', $totalValue);
            // Note: 'balance' was already incremented in the Service T+0 phase
        }
    }

    private function checkAndCompleteOrder($order)
    {
        // If all trades linked to this order are settled, mark order as filled
        $unsettledCount = $order->trades()->where('settlement_status', '!=', 'settled')->count();
        if ($unsettledCount === 0) {
            $order->update(['status' => 'filled']);
        }
    }
}