<?php

namespace App\Services;

use App\Models\User;
use App\Models\BillingRecord;
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
                    'amount' => $fee,
                    'type' => 'subscription_fee',
                    'status' => 'paid'
                ]);
            } else {
                $shortfall = $fee - $user->wallet_balance;
                $user->wallet_debt += $shortfall;
                $user->wallet_balance = 0;

                BillingRecord::create([
                    'user_id' => $user->id,
                    'amount' => $fee,
                    'type' => 'subscription_fee',
                    'status' => 'pending'
                ]);
            }

            $user->subscription_status = 'active';
            $user->last_fee_charged_at = now();
            $user->next_fee_due_at = now()->addDays(90);

            // Safety check for suspension threshold
            if ($user->wallet_debt > 5000) {
                $user->subscription_status = 'suspended';
            }

            $user->save();
        });
    }
}