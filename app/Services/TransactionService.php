<?php

namespace App\Services;

use App\Models\TransactionCharge;
use App\Models\PlatformEarning;
use App\Models\NewTransaction;
use App\Models\SystemSetting;

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
        $currency = $transaction->currency ?? 'NGN';
        $settings = SystemSetting::first();
        $rate = $settings->usd_to_ngn ?? 1000;
        $amountNg = $currency === 'NGN' ? $fee : ($currency === 'USD' ? ($fee * $rate) : $fee);

        PlatformEarning::create([
            'transaction_id' => $transaction->id,
            'amount' => $fee,
            'currency' => $currency,
            'amount_ngn' => $amountNg,
            'source' => $transaction->type
        ]);

        return $transaction;
    }
}