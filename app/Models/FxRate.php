<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    protected $table = 'fx_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'base_rate',
        'markup_percent',
        'effective_rate',
    ];

    protected $casts = [
        'base_rate' => 'float',
        'markup_percent' => 'float',
        'effective_rate' => 'float',
    ];
}
