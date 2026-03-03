<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class DemoWallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'ngn_cleared', 'ngn_uncleared', 'usd_cleared', 'usd_uncleared', 'locked', 'currency', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
