<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoAddress extends Model
{
    use HasFactory;

    protected $table = 'crypto_addresses';

    protected $fillable = [
        'user_id',
        'blockchain',
        'address',
        'private_key',
        'qr_code_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
