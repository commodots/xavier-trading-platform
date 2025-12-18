<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Model;

class KycProfile extends Model
{
    protected $fillable = [
        'user_id', 'level', 'bvn', 'nin', 
        'id_type', 'id_number', 'status', 'rejection_reason'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}