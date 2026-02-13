<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxConfig extends Model
{
    protected $table = 'fx_config';

    protected $fillable = [
        'min_markup',
        'max_markup',
        'target_margin_percent',
        'volatility_threshold',
    ];

    protected $casts = [
        'min_markup' => 'float',
        'max_markup' => 'float',
        'target_margin_percent' => 'float',
        'volatility_threshold' => 'float',
    ];
}
