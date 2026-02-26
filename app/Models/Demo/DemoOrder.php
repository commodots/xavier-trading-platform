<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;

class DemoOrder extends Model
{
    protected $fillable = [
        'user_id',
        'symbol',
        'market_type',
        'type',
        'quantity',
        'price',
        'total',
        'status'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'total' => 'float',
    ];
}