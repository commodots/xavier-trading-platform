<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycProfile extends Model
{
    use HasFactory;

    protected $table = 'kyc_profiles';

    protected $fillable = [
        'user_id', 'level', 'tier', 'daily_limit', 'bvn', 'nin', 'tin', 'id_type', 'id_number', 'status', 'rejection_reason', 'intl_passport', 'drivers_license', 'proof_of_address', 'national_id', 'id_card_front', 'id_card_back',
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
    ];

    public function getTierNameAttribute()
    {
        return match ((int) $this->tier) {
            1 => 'Basic',
            2 => 'Mid-Level',
            3 => 'Full Access',
            default => 'Unverified'
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
