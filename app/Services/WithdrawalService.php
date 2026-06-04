<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WithdrawalLimitExceededException;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalLimit;
use App\Notifications\WithdrawalInitiated;
use Exception;

class WithdrawalService
{
    /**
     * Initiate a withdrawal request
     */
    public function initiateWithdrawal(
        User $user,
        float $amount,
        string $currency,
        string $accountNumber,
        string $accountName,
        ?string $bankCode = null
    ): WithdrawalRequest {
        if ($amount <= 0) {
            throw new Exception('Withdrawal amount must be greater than zero.');
        }

        // Use the correct currency wallet — not $user->wallet which returns a collection
        $wallet = $user->wallet()->where('currency', $currency)->first();

        if (!$wallet) {
            throw new Exception("No {$currency} wallet found.");
        }

        $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

        if ($wallet->{$clearedCol} < $amount) {
            throw new InsufficientBalanceException($currency, $amount, $wallet->{$clearedCol});
        }

        // Check withdrawal limits
        $limit = WithdrawalLimit::firstOrCreate(['user_id' => $user->id], [
            'daily_limit_ngn' => 500000,
            'daily_limit_usd' => 2500,
        ]);

        if (!$limit->canWithdraw($currency, $amount)) {
            throw new WithdrawalLimitExceededException();
        }

        // Create withdrawal request
        $withdrawal = WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'bank_code' => $bankCode,
            'status' => 'pending',
            'metadata' => [
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ],
        ]);

        // Record the withdrawal in limits
        $limit->recordWithdrawal($currency, $amount);

        // Notify user
        $user->notify(new WithdrawalInitiated($withdrawal));

        // Log audit
        activity()
            ->causedBy($user)
            ->performedOn($withdrawal)
            ->event('withdrawal_initiated')
            ->log("User initiated withdrawal of {$amount} {$currency}");

        return $withdrawal;
    }

    /**
     * Approve a withdrawal
     */
    public function approveWithdrawal(WithdrawalRequest $withdrawal, User $approver): void
    {
        if ($withdrawal->status !== 'pending') {
            throw new Exception('Only pending withdrawals can be approved.');
        }

        $withdrawal->approve($approver->id);

        activity()
            ->causedBy($approver)
            ->performedOn($withdrawal)
            ->event('withdrawal_approved')
            ->log("Admin approved withdrawal request #{$withdrawal->id}");

        // Deduct from wallet
        $this->deductFromWallet($withdrawal);
    }

    /**
     * Reject a withdrawal
     */
    public function rejectWithdrawal(WithdrawalRequest $withdrawal, User $approver, string $reason): void
    {
        if ($withdrawal->status !== 'pending') {
            throw new Exception('Only pending withdrawals can be rejected.');
        }

        $withdrawal->reject($approver->id, $reason);

        // Refund daily limit counter atomically — clamp to 0 to prevent negatives
        WithdrawalLimit::where('user_id', $withdrawal->user_id)->each(function ($limit) use ($withdrawal) {
            $col = $withdrawal->currency === 'NGN' ? 'daily_withdrawn_ngn' : 'daily_withdrawn_usd';
            $limit->$col = max(0, $limit->$col - $withdrawal->amount);
            $limit->save();
        });

        activity()
            ->causedBy($approver)
            ->performedOn($withdrawal)
            ->event('withdrawal_rejected')
            ->log("Admin rejected withdrawal request #{$withdrawal->id}: {$reason}");
    }

    /**
     * Complete/finalize a withdrawal
     */
    public function completeWithdrawal(WithdrawalRequest $withdrawal): void
    {
        if ($withdrawal->status !== 'approved') {
            throw new Exception('Only approved withdrawals can be completed.');
        }

        $withdrawal->complete();

        activity()
            ->causedBy($withdrawal->user)
            ->performedOn($withdrawal)
            ->event('withdrawal_completed')
            ->log("Withdrawal request #{$withdrawal->id} completed successfully");
    }

    /**
     * Mark withdrawal as failed
     */
    public function failWithdrawal(WithdrawalRequest $withdrawal, string $reason): void
    {
        $withdrawal->update(['status' => 'failed']);

        // Refund daily limit counter atomically — clamp to 0
        WithdrawalLimit::where('user_id', $withdrawal->user_id)->each(function ($limit) use ($withdrawal) {
            $col = $withdrawal->currency === 'NGN' ? 'daily_withdrawn_ngn' : 'daily_withdrawn_usd';
            $limit->$col = max(0, $limit->$col - $withdrawal->amount);
            $limit->save();
        });

        activity()
            ->causedBy($withdrawal->user)
            ->performedOn($withdrawal)
            ->event('withdrawal_failed')
            ->log("Withdrawal request #{$withdrawal->id} failed: {$reason}");
    }

    /**
     * Deduct withdrawal amount from user's wallet
     */
    private function deductFromWallet(WithdrawalRequest $withdrawal): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($withdrawal) {
            $wallet = $withdrawal->user
                ->wallet()
                ->where('currency', $withdrawal->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $col = $withdrawal->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $wallet->decrement($col, $withdrawal->amount);

            $withdrawal->update(['status' => 'approved']);
        });
    }

    /**
     * Check for withdrawal fraud patterns
     */
    public function checkFraudPatterns(User $user, float $amount, string $currency): array
    {
        $flags = [];

        // Check for multiple withdrawals in short time
        $recentWithdrawals = WithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'completed'])
            ->where('created_at', '>', now()->subHours(1))
            ->count();

        if ($recentWithdrawals >= 3) {
            $flags[] = 'multiple_withdrawals_short_time';
        }

        // Check for unusual amount
        $avgWithdrawal = WithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'completed'])
            ->where('currency', $currency)
            ->average('amount') ?? 0;

        if ($avgWithdrawal > 0 && $amount > $avgWithdrawal * 3) {
            $flags[] = 'unusually_large_amount';
        }

        return $flags;
    }
}
