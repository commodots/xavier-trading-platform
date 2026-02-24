<?php

namespace App\Services\Settlement;

use App\Models\Trade;
use App\Models\Portfolio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CscsSettlementService
{
    public function initiateSettlement(Trade $trade): void
    {
        if (config('services.cscs.simulate_errors') && rand(1, 20) === 10) {
            throw new \Exception('CSCS Gateway connection failed');
        }

        $settlementDate = $this->calculateBusinessDays(now(), 2);

        $trade->update([
            'settlement_status' => 'pending',
            'settlement_date'   => $settlementDate->toDateString()
        ]);
    }

    public function finalizeSettlement(Trade $trade): void
    {
        if ($trade->settlement_status === 'settled') return;

        DB::transaction(function () use ($trade) {
            
            $userId = $trade->order->user_id; 
            
            
            $symbol = $trade->order->symbol; 

            // Update Portfolio
            $portfolio = Portfolio::where('user_id', $userId)
                ->where('symbol', $symbol)
                ->first();

            if ($portfolio) {
                // Shift from pending to liquid
                $portfolio->decrement('uncleared_quantity', $trade->quantity);
                $portfolio->increment('cleared_quantity', $trade->quantity);
            } else {
                Portfolio::create([
                    'user_id'            => $userId,
                    'symbol'             => $symbol,
                    'name'               => $symbol,
                    'quantity'           => $trade->quantity,
                    'cleared_quantity'   => $trade->quantity,
                    'uncleared_quantity' => 0,
                    'category'           => 'local',
                    'currency'           => 'NGN',
                    'avg_price'          => $trade->price,
                    'market_price'       => $trade->price,
                ]);
            }

            //  Finalize Wallet (Deduct locked funds completely)
            $user = User::find($userId);
            if ($user && $trade->order->side === 'buy') {
                $tradeCost = $trade->quantity * $trade->price;
                $wallet = $user->fxWallet('NGN');
                
                if ($wallet) {
                    $wallet->finalizeReservation($tradeCost); 
                }
            }

            //  Mark the Trade as fully settled 
            // (Removed 'settled_at' to match your exact Trade model fillable array)
            $trade->update([
                'settlement_status' => 'settled'
            ]);
            
            Log::info("Trade Settled: {$trade->quantity} units of {$symbol} for User {$userId}");
        });
    }

    private function calculateBusinessDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        while ($days > 0) {
            $d->addDay();
            if (!$d->isWeekend()) {
                $days--;
            }
        }
        return $d;
    }
}