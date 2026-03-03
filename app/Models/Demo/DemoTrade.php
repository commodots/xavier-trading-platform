<?php

namespace App\Models\Demo;

use App\Models\Demo\DemoOrder;
use Illuminate\Database\Eloquent\Model;

class DemoTrade extends Model
{
  protected $fillable = ['order_id', 'counterparty_order_id', 'price', 'quantity', 'fee', 'settlement_status', 'settlement_date', 'reference'];
  public function order()
  {
    return $this->belongsTo(DemoOrder::class);
  }
}
