<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'account_number',
        'currency',
        'balance',
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

    // ✅ Relationship: Wallet belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Debit wallet by amount
     */
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

    /**
     * Credit wallet by amount
     */
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
     * Reserve amount for trading (locks it)
     */
    public function reserve(float $amount): self
    {
        if ($this->balance < $amount) {
            throw new \Exception("Insufficient balance to reserve {$amount}");
        }

        $this->balance -= $amount;
        $this->locked += $amount;
        $this->save();

        return $this;
    }

    /**
     * Finalize reservation - move from locked to balance for actual trades
     */
    public function finalizeReservation(float $filledAmount): self
    {
        $this->locked -= $filledAmount;
        $this->save();

        return $this;
    }

    /**
     * Settle uncleared balance to cleared
     */
    public function settle(): self
    {
        $colUn = $this->getUnclearedColumn();
        $colCleared = $this->getClearedColumn();

        if (($this->{$colUn} ?? 0) > 0) {
            $this->{$colCleared} = ($this->{$colCleared} ?? 0) + ($this->{$colUn} ?? 0);
            $this->{$colUn} = 0;
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
