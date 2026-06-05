<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdvisoryPost extends Model
{
    protected $fillable = [
        'title',
        'content',
        'market_type',
        'risk_level',
        'asset_symbol',
        'recommendation',
        'tier', // free, pro, premium
        'is_premium',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
    ];

    public function scopeAccessibleBy(Builder $query, ?User $user): void
    {
        if (! $user) {
            $query->where('tier', 'free');
        } elseif ($user->current_tier !== 'premium' && ! $user->on_trial) {
            // Allow free and pro (regular) posts but hide premium
            $query->where('tier', '!=', 'premium');
        }
    }
}
