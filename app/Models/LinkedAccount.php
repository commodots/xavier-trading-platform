<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Model;

class LinkedAccount extends Model
{
    protected $fillable = [
        'user_id', 'type', 'currency','provider', 
        'account_name', 'account_number', 'is_verified'
    ];

    
    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}