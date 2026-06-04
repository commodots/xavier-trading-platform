<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycVerification extends Model
{
    protected $fillable = [
        'user_id',
        'verification_type',
        'verification_id',
        'status',
        'response_json',
    ];

    protected $casts = [
        'response_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
