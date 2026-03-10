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
        'trial_days',
        'maintenance_mode',
    ];
}
