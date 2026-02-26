<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class DemoWallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'equity'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
