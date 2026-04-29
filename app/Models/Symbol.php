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
    ];

    protected $casts = [
        'last_price' => 'decimal:4',
        'change' => 'decimal:4',
        'volume' => 'decimal:2',
    ];
}
