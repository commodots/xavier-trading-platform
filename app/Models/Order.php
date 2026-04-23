<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'alpaca_order_id',
        'symbol',
        'side',
        'type',
        'price',
        'quantity',
        'filled_quantity',
        'status',
        'source',
        'market',
        'currency',
        'company',
        'units',
        'amount',
        'market_price',
        'limit_price',
        'stop_price',
        'take_profit',
        'stop_loss',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
