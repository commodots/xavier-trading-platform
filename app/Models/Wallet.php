<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
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

    public function debit(float $amount, string $balanceType = 'balance'): self
    {
        if ($balanceType === 'uncleared') {
            $col = $this->getUnclearedColumn();
            $this->{$col} = ($this->{$col} ?? 0) - $amount;
        } elseif ($balanceType === 'cleared') {
            $col = $this->getClearedColumn();
            $this->{$col} = ($this->{$col} ?? 0) - $amount;
        } elseif ($balanceType === 'locked') {
            $this->locked -= $amount;
        } else {
            $this->balance -= $amount;
        }

        $this->save();
        return $this;
    }

    public function credit(float $amount, string $balanceType = 'balance'): self
    {
        if ($balanceType === 'uncleared') {
            $col = $this->getUnclearedColumn();
            $this->{$col} = ($this->{$col} ?? 0) + $amount;
        } elseif ($balanceType === 'cleared') {
            $col = $this->getClearedColumn();
            $this->{$col} = ($this->{$col} ?? 0) + $amount;
        } elseif ($balanceType === 'locked') {
            $this->locked += $amount;
        } else {
            $this->balance += $amount;
        }

        $this->save();
        return $this;
    }

    /**
     * Reserve amount for trading (Moves from cleared to locked)
     */
    public function reserve(float $amount): self
    {
        $clearedCol = $this->getClearedColumn();

        if (($this->{$clearedCol} ?? 0) < $amount) {
            throw new \Exception("Insufficient {$this->currency} cleared balance to reserve {$amount}");
        }

        // Deduct from specific currency cleared column, add to locked
        $this->{$clearedCol} -= $amount;
        $this->locked += $amount;
        $this->save();

        return $this;
    }

    public function finalizeReservation(float $filledAmount): self
    {
        $this->locked -= $filledAmount;
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
                $wallet->account_number = 'XAV'.str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            }
        });
    }
}