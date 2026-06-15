<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalLimit;

class WithdrawalProtectionService
{
    /**
     * Run all pre-withdrawal checks. Returns ['allowed' => bool, 'message' => string].
     */
    public function check(User $user, float $amount, string $currency = 'NGN'): array
    {
        if (in_array($user->subscription_status, ['suspended', 'inactive'])) {
            return ['allowed' => false, 'message' => 'Your account is suspended. Please resolve your outstanding balance.'];
        }

        if ($user->wallet_debt > 0) {
            return ['allowed' => false, 'message' => "You have an outstanding debt of ₦" . number_format($user->wallet_debt, 2) . ". Please clear it before withdrawing."];
        }

        // Daily limit check
        $limit = WithdrawalLimit::firstOrCreate(['user_id' => $user->id], [
            'daily_limit_ngn' => 500000,
            'daily_limit_usd' => 2500,
        ]);

        if (!$limit->canWithdraw($currency, $amount)) {
            $limitCol = $currency === 'NGN' ? 'daily_limit_ngn' : 'daily_limit_usd';
            $limitAmt = $currency === 'NGN' ? '₦' : '$';
            return ['allowed' => false, 'message' => "Daily withdrawal limit of {$limitAmt}" . number_format($limit->{$limitCol}, 2) . " exceeded."];
        }

        $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->first();
        $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

        if (! $wallet || ($wallet->{$clearedCol} ?? 0) < $amount) {
            return ['allowed' => false, 'message' => 'Insufficient settled (cleared) funds for this withdrawal.'];
        }

        return ['allowed' => true, 'message' => 'OK'];
    }
}
