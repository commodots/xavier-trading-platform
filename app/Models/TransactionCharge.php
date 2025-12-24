<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlatformEarning;

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


        if (!is_null($transaction) && is_object($transaction)) {
            $transaction->update(['charge' => $fee]);

            PlatformEarning::create([
                'transaction_id' => $transaction->id,
                'amount' => $fee,
                'source' => 'transaction_fee',
            ]);
        }

        return $fee;
    }
}
