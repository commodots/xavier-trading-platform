<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'customer_id',
        'market_id',
        'product_id',
        'market_account_id',
        'market_customer_id',
        'portfolio_id',
        'cash_funding_account_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
