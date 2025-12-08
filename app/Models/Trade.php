<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model {
    protected $fillable = ['order_id','counterparty_order_id','price','quantity','fee'];
    public function order(){ return $this->belongsTo(Order::class); }
}
