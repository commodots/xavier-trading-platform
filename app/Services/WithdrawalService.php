<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WithdrawalLimitExceededException;
use App\Models\Ledger;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalLimit;
use App\Notifications\NewDeviceLoginNotification;
use App\Notifications\WithdrawalApprovedNotification;
use App\Notifications\WithdrawalInitiated;
use App\Notifications\WithdrawalRejectedNotification;
use App\Notifications\WithdrawalOtpNotification;
use App\Services\Audit\AuditService;
use Exception;
use Illuminate\Support\Facades\Cache;

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

        $this->deductFromWallet($withdrawal);

        // Log audit trail for withdrawal approval
        AuditService::log(
            'withdrawal_approved',
            'withdrawal',
            $withdrawal->id,
            null,
            $withdrawal->toArray()
        );

        activity()
            ->causedBy($approver)
            ->performedOn($withdrawal)
            ->event('withdrawal_approved')
            ->log("Admin approved withdrawal request #{$withdrawal->id}");

        $withdrawal->user->notify(new WithdrawalApprovedNotification($withdrawal));
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

        // Refund daily limit counter atomically inside a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($withdrawal) {
            WithdrawalLimit::where('user_id', $withdrawal->user_id)->each(function ($limit) use ($withdrawal) {
                $col = $withdrawal->currency === 'NGN' ? 'daily_withdrawn_ngn' : 'daily_withdrawn_usd';
                $limit->$col = max(0, $limit->$col - $withdrawal->amount);
                $limit->save();
            });
        });

        activity()
            ->causedBy($approver)
            ->performedOn($withdrawal)
            ->event('withdrawal_rejected')
            ->log("Admin rejected withdrawal request #{$withdrawal->id}: {$reason}");

        $withdrawal->user->notify(new WithdrawalRejectedNotification($withdrawal));
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

        // Refund daily limit counter atomically inside a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($withdrawal) {
            WithdrawalLimit::where('user_id', $withdrawal->user_id)->each(function ($limit) use ($withdrawal) {
                $col = $withdrawal->currency === 'NGN' ? 'daily_withdrawn_ngn' : 'daily_withdrawn_usd';
                $limit->$col = max(0, $limit->$col - $withdrawal->amount);
                $limit->save();
            });
        });

        activity()
            ->causedBy($withdrawal->user)
            ->performedOn($withdrawal)
            ->event('withdrawal_failed')
            ->log("Withdrawal request #{$withdrawal->id} failed: {$reason}");
    }

    /**
     * Deduct withdrawal amount from user's wallet inside a transaction
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
            $wallet->decrement('balance', $withdrawal->amount);
            $wallet->save();

            Ledger::create([
                'user_id' => $withdrawal->user_id,
                'currency' => $withdrawal->currency,
                'amount' => $withdrawal->amount,
                'type' => 'withdrawal',
                'status' => 'completed',
                'reference' => "withdrawal-{$withdrawal->id}",
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                ],
            ]);

            activity()
                ->causedBy($withdrawal->user)
                ->performedOn($withdrawal)
                ->event('withdrawal_wallet_deducted')
                ->log("Deducted {$withdrawal->amount} {$withdrawal->currency} from wallet for withdrawal #{$withdrawal->id}");
        });
    }

    /**
     * Send withdrawal OTP to user
     */
    public function sendWithdrawalOtp(User $user): string
    {
        $otp = (string) random_int(100000, 999999);
        $cacheKey = "withdrawal_otp_{$user->id}";
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        $user->notify(new WithdrawalOtpNotification($otp));

        return $otp;
    }

    /**
     * Verify withdrawal OTP
     */
    public function verifyWithdrawalOtp(User $user, string $otp): bool
    {
        $cacheKey = "withdrawal_otp_{$user->id}";
        $cachedOtp = Cache::get($cacheKey);

        if (! $cachedOtp || ! hash_equals($otp, $cachedOtp)) {
            return false;
        }

        Cache::forget($cacheKey);
        return true;
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
