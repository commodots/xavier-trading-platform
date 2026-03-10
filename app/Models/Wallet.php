<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'account_number',
        'currency',
        'balance', // Kept for legacy compatibility if needed elsewhere
        'ngn_cleared',
        'ngn_uncleared',
        'usd_cleared',
        'usd_uncleared',
        'locked',
        'status',
    ];

    protected $casts = [
        'balance' => 'float',
        'ngn_cleared' => 'float',
        'ngn_uncleared' => 'float',
        'usd_cleared' => 'float',
        'usd_uncleared' => 'float',
        'locked' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function getClearedBalance(): float
    {
        return $this->currency === 'NGN' ? (float) $this->ngn_cleared : (float) $this->usd_cleared;
    }

    public function debit(float $amount, string $balanceType = 'cleared'): self
    {
        
        if ($this->currency === 'NGN') {
            if ($balanceType === 'cleared') $this->ngn_cleared = (float)$this->ngn_cleared - $amount;
            if ($balanceType === 'uncleared') $this->ngn_uncleared = (float)$this->ngn_uncleared - $amount;
        } else {
            if ($balanceType === 'cleared') $this->usd_cleared = (float)$this->usd_cleared - $amount;
            if ($balanceType === 'uncleared') $this->usd_uncleared = (float)$this->usd_uncleared - $amount;
        }

        if ($balanceType === 'locked') $this->locked = (float)$this->locked - $amount;

        $this->balance = (float)$this->balance - $amount;
        $this->save();

        return $this;
    }

    public function credit(float $amount, string $balanceType = 'cleared'): self
    {
        if ($this->currency === 'NGN') {
            if ($balanceType === 'cleared') $this->ngn_cleared = (float)$this->ngn_cleared + $amount;
            if ($balanceType === 'uncleared') $this->ngn_uncleared = (float)$this->ngn_uncleared + $amount;
        } else {
            if ($balanceType === 'cleared') $this->usd_cleared = (float)$this->usd_cleared + $amount;
            if ($balanceType === 'uncleared') $this->usd_uncleared = (float)$this->usd_uncleared + $amount;
        }

        if ($balanceType === 'locked') $this->locked = (float)$this->locked + $amount;

        $this->balance = (float)$this->balance + $amount;
        $this->save();

        return $this;
    }

    /**
     * Reserve amount for trading (Moves from cleared to locked)
     */
    public function reserve(float $amount): self
    {
        $clearedCol = $this->getClearedColumn();
        if ((float) $this->{$clearedCol} < $amount) {
            throw new \Exception("Insufficient cleared funds.");
        }

        $this->decrement($clearedCol, $amount);
        $this->increment('locked', $amount);

        return $this->fresh();
    }
    public function finalizeReservation(float $filledAmount): self
    {
        $this->locked -= $filledAmount;
        $this->balance -= $filledAmount;
        $this->save();

        return $this;
    }

    /**
     * Settle uncleared balance to cleared
     * Accepts specific amount (from CSV). If null, settles all uncleared.
     */
    public function settle(float $amount = null): self
    {
        $colUn = $this->getUnclearedColumn();
        $colCleared = $this->getClearedColumn();

        $settleAmount = $amount ?? $this->{$colUn};

        if ($settleAmount > 0 && ($this->{$colUn} ?? 0) >= $settleAmount) {
            $this->{$colCleared} = ($this->{$colCleared} ?? 0) + $settleAmount;
            $this->{$colUn} -= $settleAmount;
            $this->save();
        }

        return $this;
    }

    protected function getClearedColumn(): string
    {
        return $this->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
    }
    protected function getUnclearedColumn(): string
    {
        return $this->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
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
