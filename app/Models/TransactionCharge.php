<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlatformEarning;
use App\Models\SystemSetting;

class TransactionCharge extends Model
{
    protected $fillable = [
        'transaction_type',
        'charge_type',
        'value',
        'active'
    ];

    public static function calculate($type, $amount, $transaction = null)
    {

        $charge = self::where('transaction_type', $type)
            ->where('active', true)
            ->first();

        $fee = 0;
        if ($charge) {
            $fee = $charge->charge_type === 'percentage'
                ? ($amount * $charge->value / 100)
                : $charge->value;
        }


        if ($transaction && is_object($transaction)) {
            $transaction->update(['charge' => $fee]);
            $currency = $transaction->currency;

            if (empty($currency)) {
                $currency = 'NGN';
            }

            $settings = SystemSetting::first();

            $rate = ($settings && $settings->usd_to_ngn > 0) ? $settings->usd_to_ngn : 1000;

            $amountNg = ($currency === 'USD') ? ($fee * $rate) : $fee;

            PlatformEarning::create([
                'transaction_id' => $transaction->id,
                'amount' => $fee,
                'currency' => $currency,
                'amount_ngn' => $amountNg,
                'source' => 'transaction_fee',
            ]);
        }

        return $fee;
    }
}
