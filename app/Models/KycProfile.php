<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\KycService;

class KycProfile extends Model
{
    use HasFactory;

    protected $table = 'kyc_profiles';

    protected $fillable = [
        'user_id',
        'level',
        'tier',
        'daily_limit',
        'currency',
        'bvn',
        'nin',
        'tin',
        'first_name',
        'last_name',
        'id_type',
        'id_number',
        'status',
        'rejection_reason',
        'intl_passport',
        'drivers_license',
        'proof_of_address',
        'national_id',
        'id_card_front',
        'id_card_back',
        'photo',
        'document',
        'verified_at',
        'meta',
    ];

    protected $hidden = [
        'bvn',
        'nin',
        'tin',
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'bvn' => 'encrypted',
        'nin' => 'encrypted',
        'tin' => 'encrypted',
        'meta' => 'array',
        'verified_at' => 'datetime',
    ];

    /**
     * Get readable tier name
     */
    public function getTierNameAttribute()
    {
        return match ((int) $this->tier) {
            1 => 'Basic',
            2 => 'Mid-Level',
            3 => 'Full Access',
            default => 'Unverified'
        };
    }

    /**
     * Get masked BVN for safe display
     */
    public function getMaskedBvnAttribute()
    {
        return KycService::maskPii($this->bvn);
    }

    /**
     * Get masked NIN for safe display
     */
    public function getMaskedNinAttribute()
    {
        return KycService::maskPii($this->nin);
    }

    /**
     * Get masked TIN for safe display
     */
    public function getMaskedTinAttribute()
    {
        return KycService::maskPii($this->tin);
    }

    /**
     * Check if KYC is verified
     */
    public function isVerified(): bool
    {
        return in_array($this->status, ['approved', 'verified']);
    }

    /**
     * Check if KYC verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if KYC verification was rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get formatted response for API
     */
    public function toFormattedArray(): array
    {
        return KycService::formatKycResponse($this);
    }

    /**
     * Get KYC data with masked PII
     */
    public function toMaskedArray(): array
    {
        return KycService::getMaskedKycData($this);
    }

    /**
     * Relationship: KYC belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
