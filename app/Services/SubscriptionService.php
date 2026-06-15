<?php

namespace App\Services;

use App\Models\User;
use App\Models\BillingRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionService
{
    protected $fee = 1000; // ₦1,000 quarterly fee

    /**
     * Process the quarterly platform fee for a user.
     */
    public function chargePlatformFee(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Skip if inactive for more than 60 days
            if ($user->last_active_at && $user->last_active_at->diffInDays(now()) > 60) {
                $user->update(['subscription_status' => 'inactive']);
                return;
            }

            $wallet = $user->wallets()->where('currency', 'NGN')->lockForUpdate()->first();

            if ($wallet && $wallet->ngn_cleared >= $this->fee) {
                $wallet->decrement('ngn_cleared', $this->fee);
                $wallet->decrement('balance', $this->fee);

                BillingRecord::create([
                    'user_id' => $user->id,
                    'amount'  => $this->fee,
                    'type'    => 'subscription_fee',
                    'status'  => 'paid',
                ]);

                $user->notify(new \App\Notifications\BillingAlertNotification('charged', $this->fee));
            } else {
                $currentBalance = $wallet ? (float) $wallet->ngn_cleared : 0.0;
                $shortfall = $this->fee - $currentBalance;

                if ($wallet && $currentBalance > 0) {
                    $wallet->update(['ngn_cleared' => 0, 'balance' => max(0, $wallet->balance - $currentBalance)]);
                }

                $user->increment('wallet_debt', $shortfall);

                BillingRecord::create([
                    'user_id' => $user->id,
                    'amount'  => $this->fee,
                    'type'    => 'subscription_fee',
                    'status'  => 'pending_debt',
                ]);

                $user->notify(new \App\Notifications\BillingAlertNotification('debt', $shortfall));
            }

            $user->refresh();
            $user->update([
                'subscription_status' => $user->wallet_debt > 5000 ? 'suspended' : 'active',
                'last_fee_charged_at' => now(),
                'next_fee_due_at'     => now()->addDays(90),
            ]);
        });
    }

    /**
     * Logic to clear debt when a user tops up their wallet.
     */
    public function reconcileDebt(User $user, float $topupAmount): float
    {
        if ($user->wallet_debt <= 0) return $topupAmount;

        $paymentToDebt = min($user->wallet_debt, $topupAmount);

        $user->decrement('wallet_debt', $paymentToDebt);
        $user->refresh();

        if ($user->wallet_debt < 5000 && $user->subscription_status === 'suspended') {
            $user->update(['subscription_status' => 'active']);
        }

        BillingRecord::create([
            'user_id' => $user->id,
            'amount' => $paymentToDebt,
            'type' => 'debt_repayment',
            'status' => 'paid'
        ]);

        return $topupAmount - $paymentToDebt;
    }
}
