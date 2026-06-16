<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiskFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // Risk flag type constants
    const TYPE_MULTIPLE_KYC_FAILURES = 'MULTIPLE_KYC_FAILURES';
    const TYPE_MULTIPLE_DEVICES = 'MULTIPLE_DEVICES';
    const TYPE_SUSPICIOUS_WITHDRAWAL = 'SUSPICIOUS_WITHDRAWAL';
    const TYPE_HIGH_DEBT = 'HIGH_DEBT';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get display label for the risk type
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MULTIPLE_KYC_FAILURES => 'Multiple KYC Failures',
            self::TYPE_MULTIPLE_DEVICES => 'Multiple Devices Detected',
            self::TYPE_SUSPICIOUS_WITHDRAWAL => 'Suspicious Withdrawal',
            self::TYPE_HIGH_DEBT => 'High Debt',
            default => str_replace('_', ' ', $this->type),
        };
    }

    /**
     * Get severity color class
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'bg-red-600',
            'high' => 'bg-orange-500',
            'medium' => 'bg-yellow-500',
            'low' => 'bg-blue-500',
            default => 'bg-gray-500',
        };
    }
}