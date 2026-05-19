<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AdvisoryPost extends Model
{
    protected $fillable = [
        'title', 
        'content', 
        'market_type', 
        'risk_level',
        'recommendation', // BUY, SELL, HOLD
        'tier', // free, pro, premium
        'is_premium'
    ];

    protected $casts = [
        'is_premium' => 'boolean',
    ];

    public function scopeAccessibleBy(Builder $query, ?User $user): void
    {
        if (!$user || $user->current_tier !== 'premium') {
            $query->where('is_premium', false)->where('tier', 'free');
        }
    }
}