<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Trade extends Model
{
    protected $fillable = [
        'order_id', 'counterparty_order_id', 'price', 'quantity', 'fee', 'settlement_status', 'settlement_date', 'reference',
        'user_id', 'pair', 'type', 'amount', 'entry_price', 'exit_price', 'profit_loss', 'status',
        'is_settled',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'fee' => 'decimal:8',
        'amount' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'profit_loss' => 'decimal:8',
        'settlement_date' => 'datetime',
        'is_settled' => 'boolean'
    ];

    public function scopeUnsettled(Builder $query): void
    {
        $query->where('is_settled', false);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
