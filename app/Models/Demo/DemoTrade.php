<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;

class DemoTrade extends Model
{
    protected $fillable = ['order_id', 'counterparty_order_id', 'price', 'quantity', 'fee', 'settlement_status', 'settlement_date', 'reference', 'user_id', 'pair', 'type', 'amount', 'entry_price', 'exit_price', 'profit_loss', 'status'];

    public function order()
    {
        return $this->belongsTo(DemoOrder::class);
    }
}
