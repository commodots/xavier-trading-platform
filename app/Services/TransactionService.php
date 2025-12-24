<?php

namespace App\Services;

use App\Models\TransactionCharge;
use App\Models\PlatformEarning;
use App\Models\NewTransaction;

class TransactionService {
    public static function applyFees(NewTransaction $transaction) {
        // Fetch active charge logic 
        $chargeConfig = TransactionCharge::where('transaction_type', $transaction->type)
            ->where('active', true)
            ->first();

        $fee = 0;
        if ($chargeConfig) {
            // Calculate based on type 
            $fee = $chargeConfig->charge_type === 'percentage' 
                ? ($transaction->amount * $chargeConfig->value / 100)
                : $chargeConfig->value;
        }

        // Update transaction record
        $transaction->charge = $fee;
        $transaction->net_amount = ($transaction->type === 'deposit') 
            ? ($transaction->amount - $fee) 
            : ($transaction->amount + $fee);
        $transaction->save();

        // Save to Platform Earnings 
        PlatformEarning::create([
            'transaction_id' => $transaction->id,
            'amount' => $fee,
            'source' => $transaction->type
        ]);

        return $transaction;
    }
}