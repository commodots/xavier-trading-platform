<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueRecord extends Model
{
    protected $fillable = [
        'source',
        'transaction_id',
        'currency',
        'amount',
        'fee_percentage',
        'description',
        'record_date',
    ];

    protected $casts = [
        'record_date' => 'date',
    ];

    public function transaction()
    {
        return $this->belongsTo(NewTransaction::class, 'transaction_id');
    }
}