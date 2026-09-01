<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedIncomeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_income_investment_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'status',
        'reference',
        'transaction_id',
        'ledger_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(
            FixedIncomeInvestment::class,
            'fixed_income_investment_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}