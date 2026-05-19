<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingRecord extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'type',   // subscription_fee, wallet_topup, adjustment
        'status', // pending, paid, failed
        'reference'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}