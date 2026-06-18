<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxPair extends Model
{
    protected $table = 'fx_pairs';

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'buy_rate',
        'sell_rate',
        'active',
    ];

    protected $casts = [
        'buy_rate' => 'float',
        'sell_rate' => 'float',
        'active' => 'boolean',
    ];
}