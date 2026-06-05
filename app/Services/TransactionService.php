<?php

namespace App\Services;

use App\Models\TransactionCharge;
use App\Models\PlatformEarning;
use App\Models\NewTransaction;
use App\Models\SystemSetting;

class TransactionService {
    public static function applyFees(NewTransaction $transaction): NewTransaction
    {
        $chargeConfig = TransactionCharge::where('transaction_type', $transaction->type)
            ->where('active', true)
            ->first();

        $fee = 0;
        if ($chargeConfig) {
            $fee = $chargeConfig->charge_type === 'percentage'
                ? ($transaction->amount * $chargeConfig->value / 100)
                : (float) $chargeConfig->value;

            // Clamp fee: never negative, never exceeds transaction amount
            $fee = max(0, min($fee, $transaction->amount));
        }

        $transaction->charge = $fee;
        $transaction->net_amount = ($transaction->type === 'deposit')
            ? ($transaction->amount - $fee)
            : ($transaction->amount + $fee);
        $transaction->save();

        if ($fee > 0) {
            $currency = $transaction->currency ?? 'NGN';
            $settings = SystemSetting::first();
            $rate = (float) ($settings?->usd_to_ngn ?? 1000);
            $amountNgn = $currency === 'USD' ? ($fee * $rate) : $fee;

            PlatformEarning::create([
                'transaction_id' => $transaction->id,
                'amount'         => $fee,
                'currency'       => $currency,
                'amount_ngn'     => $amountNgn,
                'source'         => $transaction->type,
            ]);
        }

        return $transaction;
    }
}