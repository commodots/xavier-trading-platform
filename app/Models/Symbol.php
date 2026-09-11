<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    protected $table = 'symbols';

    protected $fillable = [
        'symbol',
        'name',
        'type',
        'exchange',
        'last_price',
        'change',
        'volume',
        'provider',
        'provider_symbol_id',
        'market_id',
        'product_id',
        'isin',
        'provider_symbol_type',
        'provider_metadata',
    ];

    protected $casts = [
        'last_price' => 'decimal:4',
        'change' => 'decimal:4',
        'volume' => 'decimal:2',
        'provider_metadata' => 'array',
    ];
}
