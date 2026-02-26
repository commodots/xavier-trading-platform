<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class DemoPortfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'name',
        'category',
        'quantity',
        'cleared_quantity',
        'uncleared_quantity',
        'avg_price',
        'market_price',
        'currency'
    ];

    protected $casts = [
        'quantity' => 'float',
        'cleared_quantity' => 'float',
        'uncleared_quantity' => 'float',
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