<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'name',
        'category',
        'quantity',
        'avg_price',
        'market_price',
        'currency'
    ];

    protected $casts = [
        'quantity' => 'float',
        'avg_price' => 'float',
        'market_price' => 'float',
    ];

    protected $appends = ['total_value'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate Total Value (Qty * Market Price)
     */
    public function getTotalValueAttribute()
    {
        return $this->quantity * $this->market_price;
    }
}