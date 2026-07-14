<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'market_value',
        'cost_basis',
        'cash_balance',
        'gain',
        'roi',
        'currency',
        'snapshot_date',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}