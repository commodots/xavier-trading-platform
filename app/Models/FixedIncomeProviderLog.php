<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedIncomeProviderLog extends Model
{
    protected $fillable = [
        'fixed_income_investment_id',
        'provider',
        'operation',
        'request_reference',
        'provider_reference',
        'http_status',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function investment()
    {
        return $this->belongsTo(
            FixedIncomeInvestment::class,
            'fixed_income_investment_id'
        );
    }
}