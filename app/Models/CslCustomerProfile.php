<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CslCustomerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'mobile_phone_no',
        'gender_cd',
        'address_street',
        'address_city',
        'address_country_cd',
        'bank_cd',
        'bank_account_name',
        'bank_account_no',
        'bank_branch_name',
        'bank_address_details',
        'bank_bvn_no',
        'customer_id',
        'status',
        'last_error',
        'submitted_at',
        'approved_at',
    ];

    protected $hidden = [
        'bank_account_no',
        'bank_bvn_no',
        'customer_id',
        'last_error',
    ];

    protected $casts = [
        'bank_account_no' => 'encrypted',
        'bank_bvn_no' => 'encrypted',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
