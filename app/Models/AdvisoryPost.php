<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvisoryPost extends Model
{
    protected $fillable = [
        'title', 
        'content', 
        'market_type', 
        'risk_level', 
        'is_premium'
    ];

    protected $casts = [
        'is_premium' => 'boolean',
    ];
}