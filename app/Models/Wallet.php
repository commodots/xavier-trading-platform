<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'account_number',
        'currency',
        'balance',
        'locked',
        'status',
    ];

    // ✅ Relationship: Wallet belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
	
	public function transactions()
	{
		return $this->hasMany(WalletTransaction::class);
	}

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (empty($wallet->account_number)) {
                $wallet->account_number = 'XAV' . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            }
        });
    }
}
