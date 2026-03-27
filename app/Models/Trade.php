<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'order_id', 'counterparty_order_id', 'price', 'quantity', 'fee', 'settlement_status', 'settlement_date', 'reference',
        'user_id', 'pair', 'type', 'amount', 'entry_price', 'exit_price', 'profit_loss', 'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
