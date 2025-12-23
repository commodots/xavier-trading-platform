<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCharge extends Model
{
    public static function calculate($type, $amount)
    {
    
        $charge = self::where('transaction_type', $type)->first();

        if (!$charge) {
            return 0;
        }

        // Formula: Flat Fee + (Amount * Percentage / 100)
        $flatFee = $charge->flat_fee ?? 0;
        $percentageFee = ($amount * (($charge->percentage_fee ?? 0) / 100));

        return $flatFee + $percentageFee;
    }
}
