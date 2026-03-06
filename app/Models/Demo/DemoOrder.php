<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Demo\DemoTrade;

class DemoOrder extends Model
{
    protected $fillable = [
        'user_id',
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
        'market_price'
    ];


    protected $casts = [
        'quantity'     => 'decimal:8',
        'price' => 'float',
        'amount' => 'float',
        'market_price' => 'float',
    ];

    public function trades()
    {
        return $this->hasMany(DemoTrade::class, 'order_id');
    }
}
