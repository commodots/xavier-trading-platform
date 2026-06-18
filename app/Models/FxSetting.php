<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxSetting extends Model
{
    protected $table = 'fx_settings';

    protected $fillable = [
        'provider',
        'enabled',
        'auto_convert_stocks',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_convert_stocks' => 'boolean',
    ];
}