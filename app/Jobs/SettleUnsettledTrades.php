<?php

namespace App\Jobs;

use App\Models\Trade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SettleUnsettledTrades implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Trade::where('is_settled', false)
            ->whereNotNull('settlement_date')
            ->where('settlement_date', '<=', now())
            ->with(['user', 'order'])
            ->chunkById(100, function ($trades) {
                foreach ($trades as $trade) {
                    $this->settleTrade($trade);
                }
            });
    }

    private function settleTrade(Trade $trade): void
    {
        try {
            if (!$trade->user) {
                \Log::warning("SettleUnsettledTrades: No user for trade {$trade->id}");
                return;
            }

            $currency     = $trade->currency === 'USD' ? 'USD' : 'NGN';
            $unclearedCol = $trade->currency === 'USD' ? 'usd_uncleared' : 'ngn_uncleared';
            $clearedCol   = $trade->currency === 'USD' ? 'usd_cleared'   : 'ngn_cleared';

            $tradeWallet = $trade->user->wallet()
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$tradeWallet) {
                \Log::warning("SettleUnsettledTrades: No {$currency} wallet for user {$trade->user_id}");
                $trade->update(['settlement_status' => 'failed']);
                return;
            }

            $settledAmount = $trade->total_amount ?? ($trade->quantity * $trade->price);

            \Illuminate\Support\Facades\DB::transaction(function () use ($trade, $tradeWallet, $unclearedCol, $clearedCol, $settledAmount) {
                $tradeWallet->decrement($unclearedCol, $settledAmount);
                $tradeWallet->increment($clearedCol, $settledAmount);

                $trade->update([
                    'is_settled'        => true,
                    'settlement_status' => 'completed',
                ]);
            });

            activity()
                ->causedBy($trade->user)
                ->performedOn($trade)
                ->event('settlement_completed')
                ->log("Trade {$trade->id} settled successfully");

        } catch (\Exception $e) {
            \Log::error("Settlement failed for trade {$trade->id}: {$e->getMessage()}");
            $trade->update(['settlement_status' => 'failed']);
        }
    }
}
