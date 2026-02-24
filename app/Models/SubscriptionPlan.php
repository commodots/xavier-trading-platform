<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

   
    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'features',
        'paystack_plan_code',
    ];

    
    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}