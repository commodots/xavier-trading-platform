<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    protected $table = 'user_kycs';
    
    protected $fillable = [
        'user_id',
        'provider',
        'id_type',
        'id_value',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'phone_number',
        'email',
        'photo',
        'bvn',
        'nin',
        'address',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'date_of_birth' => 'date',
    ];

    // ✅ Relationship: KYC belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
