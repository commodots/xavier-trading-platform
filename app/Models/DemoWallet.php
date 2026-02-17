<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoWallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'equity'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}