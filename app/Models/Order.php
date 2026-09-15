<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'alpaca_order_id',
        'symbol',
        'side',
        'type',
        'price',
        'quantity',
        'filled_quantity',
        'status',
        'source',
        'market',
        'currency',
        'company',
        'units',
        'amount',
        'market_price',
        'limit_price',
        'stop_price',
        'take_profit',
        'stop_loss',
        'position_type',
        'provider',
        'provider_order_id',
        'provider_market_account_id',
        'time_in_force',
        'expiry_date',
        'provider_request',
        'provider_response',
        'provider_submitted_at',
        'provider_client_reference',
        'last_reconciled_at',
        'reconciliation_status',
        'provider_cancellation_status',
        'provider_cancel_requested_at',
    ];

    protected $casts = [
        'provider_request' => 'array',
        'provider_response' => 'array',
        'provider_submitted_at' => 'datetime',
        'expiry_date' => 'date',
        'last_reconciled_at' => 'datetime',
        'provider_cancel_requested_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
