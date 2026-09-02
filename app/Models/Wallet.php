<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_number',
        'currency',
        'balance',
        'ngn_cleared',
        'ngn_uncleared',
        'usd_cleared',
        'usd_uncleared',
        'cleared_balance',
        'uncleared_balance',
        'locked',
        'status',
    ];

    protected $casts = [
        'balance' => 'float',
        'ngn_cleared' => 'float',
        'ngn_uncleared' => 'float',
        'usd_cleared' => 'float',
        'usd_uncleared' => 'float',
        'cleared_balance' => 'float',
        'uncleared_balance' => 'float',
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
        $normalizedBalanceType = match ($balanceType) {
            'balance', 'available', 'total' => 'cleared',
            default => $balanceType,
        };

        if ($this->currency === 'NGN') {
            if ($normalizedBalanceType === 'cleared') {
                $this->ngn_cleared = (float) $this->ngn_cleared - $amount;
            }
            if ($normalizedBalanceType === 'uncleared') {
                $this->ngn_uncleared = (float) $this->ngn_uncleared - $amount;
            }
        } else {
            if ($normalizedBalanceType === 'cleared') {
                $this->usd_cleared = (float) $this->usd_cleared - $amount;
            }
            if ($normalizedBalanceType === 'uncleared') {
                $this->usd_uncleared = (float) $this->usd_uncleared - $amount;
            }
        }

        if ($normalizedBalanceType === 'locked') {
            $this->locked = (float) $this->locked - $amount;
        }

        $this->refreshBalance(true);

        return $this;
    }

    public function credit(float $amount, string $balanceType = 'cleared'): self
    {
        $normalizedBalanceType = match ($balanceType) {
            'balance', 'available', 'total' => 'cleared',
            default => $balanceType,
        };

        if ($this->currency === 'NGN') {
            if ($normalizedBalanceType === 'cleared') {
                $this->ngn_cleared = (float) $this->ngn_cleared + $amount;
            }
            if ($normalizedBalanceType === 'uncleared') {
                $this->ngn_uncleared = (float) $this->ngn_uncleared + $amount;
            }
        } else {
            if ($normalizedBalanceType === 'cleared') {
                $this->usd_cleared = (float) $this->usd_cleared + $amount;
            }
            if ($normalizedBalanceType === 'uncleared') {
                $this->usd_uncleared = (float) $this->usd_uncleared + $amount;
            }
        }

        if ($normalizedBalanceType === 'locked') {
            $this->locked = (float) $this->locked + $amount;
        }

        $this->refreshBalance(true);

        return $this;
    }

    /**
     * Reserve amount for trading (Moves from cleared to locked)
     */
    public function reserve(float $amount): self
    {
        $clearedCol = $this->getClearedColumn();
        if ((float) $this->{$clearedCol} < $amount) {
            throw new \Exception('Insufficient cleared funds.');
        }

        $this->decrement($clearedCol, $amount, []);
        $this->increment('locked', $amount, []);

        $this->refresh();

        return $this->refreshBalance();
    }

    public function releaseReservation(float $amount): self
    {
        if ((float) $this->locked < $amount) {
            throw new \Exception('Insufficient locked funds.');
        }

        $clearedCol = $this->getClearedColumn();
        $this->{$clearedCol} = (float) $this->{$clearedCol} + $amount;
        $this->locked = (float) $this->locked - $amount;

        return $this->refreshBalance();
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
    public function settle(?float $amount = null): self
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

    public function getClearedBalanceAttribute($value): float
    {
        $column = $this->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

        return (float) ($this->attributes[$column] ?? $value ?? 0);
    }

    public function setClearedBalanceAttribute($value): void
    {
        $this->setCurrencyBalanceValue('cleared', (float) $value);
    }

    public function getUnclearedBalanceAttribute($value): float
    {
        $column = $this->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';

        return (float) ($this->attributes[$column] ?? $value ?? 0);
    }

    public function setUnclearedBalanceAttribute($value): void
    {
        $this->setCurrencyBalanceValue('uncleared', (float) $value);
    }

    protected function getClearedColumn(): string
    {
        return $this->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
    }

    protected function getUnclearedColumn(): string
    {
        return $this->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
    }

    protected function setCurrencyBalanceValue(string $balanceType, float $value): void
    {
        $column = $balanceType === 'cleared'
            ? ($this->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared')
            : ($this->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared');

        $this->attributes[$column] = $value;

        if ($this->shouldPersistLegacyBalanceColumns()) {
            $this->attributes[$balanceType === 'cleared' ? 'cleared_balance' : 'uncleared_balance'] = $value;
        }
    }

    protected function shouldPersistLegacyBalanceColumns(): bool
    {
        return Schema::hasColumn($this->getTable(), 'cleared_balance') && Schema::hasColumn($this->getTable(), 'uncleared_balance');
    }

    protected function syncLegacyBalanceColumns(): void
    {
        if (! $this->shouldPersistLegacyBalanceColumns()) {
            return;
        }

        $this->attributes['cleared_balance'] = $this->currency === 'NGN'
            ? ($this->attributes['ngn_cleared'] ?? 0)
            : ($this->attributes['usd_cleared'] ?? 0);

        $this->attributes['uncleared_balance'] = $this->currency === 'NGN'
            ? ($this->attributes['ngn_uncleared'] ?? 0)
            : ($this->attributes['usd_uncleared'] ?? 0);
    }

    public function save(array $options = []): bool
    {
        $this->syncLegacyBalanceColumns();

        return parent::save($options);
    }

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (empty($wallet->account_number)) {
                $wallet->account_number = 'XAV'.str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Recompute and persist the total balance from sub-columns.
     * Includes cleared + uncleared + locked amounts.
     *
     * @param  bool  $save  Whether to persist immediately (set false when called from debit/credit)
     */
    public function refreshBalance(bool $save = true): self
    {
        if ($this->currency === 'NGN') {
            $this->balance = (float) $this->ngn_cleared + (float) $this->ngn_uncleared + (float) $this->locked;
        } else {
            $this->balance = (float) $this->usd_cleared + (float) $this->usd_uncleared + (float) $this->locked;
        }

        $this->syncLegacyBalanceColumns();

        if ($save) {
            $this->save();
        }

        return $this;
    }
}
