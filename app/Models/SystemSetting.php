<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
        'support_email',
        'base_currency',
        'trading_fee',
        'withdrawal_fee',
        'crypto_spread',
        'crypto_fee',
        'max_trade_amount',
        'trial_days',
        'maintenance_mode',
    ];
}
