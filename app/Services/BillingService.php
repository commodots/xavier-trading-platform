<?php

namespace App\Services;

use App\Models\User;
use App\Models\BillingRecord;
use App\Notifications\AccountSuspendedNotification;
use App\Notifications\BillingAlertNotification;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function chargePlatformFee(User $user): void
    {
        $fee = 1000;

        DB::transaction(function () use ($user, $fee) {
            if ($user->wallet_balance >= $fee) {
                $user->wallet_balance -= $fee;

                BillingRecord::create([
                    'user_id' => $user->id,
                    'amount'  => $fee,
                    'type'    => 'subscription_fee',
                    'status'  => 'paid',
                ]);
            } else {
                $shortfall = $fee - $user->wallet_balance;
                $user->wallet_debt += $shortfall;
                $user->wallet_balance = 0;

                BillingRecord::create([
                    'user_id' => $user->id,
                    'amount'  => $fee,
                    'type'    => 'subscription_fee',
                    'status'  => 'pending',
                ]);

                // Notify user of debt creation
                $user->notify(new BillingAlertNotification($shortfall, 'Insufficient wallet balance'));
            }

            $user->subscription_status = 'active';
            $user->last_fee_charged_at = now();
            $user->next_fee_due_at     = now()->addDays(90);

            if ($user->wallet_debt > 5000) {
                $user->subscription_status = 'suspended';
                $user->notify(new AccountSuspendedNotification('Debt limit exceeded — outstanding balance ₦' . number_format($user->wallet_debt, 2)));
            }

            $user->save();
        });
    }

    /**
     * Clear debt when user tops up wallet.
     */
    public function clearDebt(User $user, float $topUpAmount): void
    {
        if ($user->wallet_debt <= 0) return;

        $deduct = min($topUpAmount, $user->wallet_debt);
        $user->wallet_debt    -= $deduct;
        $user->wallet_balance += ($topUpAmount - $deduct);

        if ($user->wallet_debt <= 0 && $user->subscription_status === 'suspended') {
            $user->subscription_status = 'active';
        }

        $user->save();
    }
}