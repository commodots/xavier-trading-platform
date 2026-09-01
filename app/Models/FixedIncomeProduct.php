<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedIncomeProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'currency',
        'issuer',
        'status',
        'minimum_amount',
        'maximum_amount',
        'maximum_open_ended',
        'start_date',
        'end_date',
        'open_ended',
        'interest_rate',
        'rate_type',
        'interest_frequency',
        'tenor_days',
        'early_withdrawal_allowed',
        'early_withdrawal_penalty',
        'subscription_fee',
        'subscription_fee_type',
        'maximum_capacity',
        'execution_mode',
        'provider',
        'allow_reinvestment',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',

        'minimum_amount' => 'float',
        'maximum_amount' => 'float',
        'interest_rate' => 'float',
        'early_withdrawal_penalty' => 'float',
        'subscription_fee' => 'float',
        'maximum_capacity' => 'float',

        'maximum_open_ended' => 'boolean',
        'open_ended' => 'boolean',
        'early_withdrawal_allowed' => 'boolean',
        'allow_reinvestment' => 'boolean',

        'metadata' => 'array',
    ];

    public function investments(): HasMany
    {
        return $this->hasMany(FixedIncomeInvestment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOpenEnded(): bool
    {
        return $this->open_ended === true;
    }

    public function hasMaximumAmount(): bool
    {
        return ! $this->maximum_open_ended && $this->maximum_amount !== null;
    }

    public function acceptsAmount(float $amount): bool
    {
        if ($amount < $this->minimum_amount) {
            return false;
        }

        if ($this->hasMaximumAmount() && $amount > $this->maximum_amount) {
            return false;
        }

        return true;
    }
}