<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxConversion extends Model
{
    protected $table = 'fx_conversions';

    protected $fillable = [
        'user_id',
        'provider',
        'reference',
        'from_currency',
        'to_currency',
        'amount',
        'rate',
        'converted_amount',
        'status',
        'response',
    ];

    protected $casts = [
        'amount' => 'float',
        'rate' => 'float',
        'converted_amount' => 'float',
        'response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}