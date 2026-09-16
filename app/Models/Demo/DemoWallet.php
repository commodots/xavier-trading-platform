<?php

namespace App\Models\Demo;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoWallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'ngn_cleared', 'ngn_uncleared', 'usd_cleared', 'usd_uncleared', 'locked', 'currency', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
