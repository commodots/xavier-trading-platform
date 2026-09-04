<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedIncomeInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fixed_income_product_id',
        'reference',
        'idempotency_key',
        'principal_amount',
        'currency',
        'interest_rate',
        'rate_type',
        'expected_interest',
        'expected_maturity_amount',
        'actual_interest',
        'actual_maturity_amount',
        'status',
        'investment_date',
        'execution_date',
        'maturity_date',
        'redeemed_at',
        'funding_method',
        'execution_mode',
        'provider',
        'provider_reference',
        'reinvestment_enabled',
        'reserved_amount',
        'funded_at',
        'last_status_at',
        'metadata',
    ];

    protected $casts = [
        'principal_amount' => 'float',
        'interest_rate' => 'float',
        'expected_interest' => 'float',
        'expected_maturity_amount' => 'float',
        'actual_interest' => 'float',
        'actual_maturity_amount' => 'float',

        'investment_date' => 'datetime',
        'execution_date' => 'datetime',
        'maturity_date' => 'datetime',
        'redeemed_at' => 'datetime',

        'reinvestment_enabled' => 'boolean',

        'reserved_amount' => 'float',
        'funded_at' => 'datetime',
        'last_status_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            FixedIncomeProduct::class,
            'fixed_income_product_id'
        );
    }

    public function transactions()
    {
        return $this->hasMany(
            FixedIncomeTransaction::class,
            'fixed_income_investment_id'
        );
    }
}
