<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $fillable = [
        'user_id',
        'trade_id',
        'amount',
        'type' // trade_fee, subscription_fee
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trade()
    {
        return $this->belongsTo(Trade::class);
    }
}