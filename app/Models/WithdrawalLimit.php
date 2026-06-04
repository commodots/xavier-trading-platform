<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'daily_limit_ngn',
        'daily_limit_usd',
        'daily_withdrawn_ngn',
        'daily_withdrawn_usd',
        'last_reset_at',
        'cooldown_until',
    ];

    protected $casts = [
        'last_reset_at' => 'datetime',
        'cooldown_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canWithdraw(string $currency, float $amount): bool
    {
        if ($this->cooldown_until && $this->cooldown_until->isFuture()) {
            return false;
        }

        // Reset daily limits if it hasn't been reset today
        if (!$this->last_reset_at || !$this->last_reset_at->isToday()) {
            $this->reset();
        }

        if ($currency === 'NGN') {
            return $this->daily_withdrawn_ngn + $amount <= $this->daily_limit_ngn;
        } else {
            return $this->daily_withdrawn_usd + $amount <= $this->daily_limit_usd;
        }
    }

    public function recordWithdrawal(string $currency, float $amount)
    {
        if ($currency === 'NGN') {
            $this->daily_withdrawn_ngn += $amount;
        } else {
            $this->daily_withdrawn_usd += $amount;
        }

        $this->save();
    }

    public function reset()
    {
        $this->update([
            'daily_withdrawn_ngn' => 0,
            'daily_withdrawn_usd' => 0,
            'last_reset_at' => now(),
        ]);
    }

    public function setCooldown(int $hours = 24)
    {
        $this->update(['cooldown_until' => now()->addHours($hours)]);
    }
}
