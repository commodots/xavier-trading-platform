<?php

namespace App\Services;

use App\Models\User;
use App\Models\BillingRecord;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * Charge the quarterly platform fee.
     * Delegates to SubscriptionService — single source of truth for fee logic.
     */
    public function chargePlatformFee(User $user): void
    {
        app(SubscriptionService::class)->chargePlatformFee($user);
    }

    /**
     * Clear debt when user tops up wallet.
     * Delegates to SubscriptionService for consistent debt reconciliation.
     */
    public function clearDebt(User $user, float $topUpAmount): float
    {
        return app(SubscriptionService::class)->reconcileDebt($user, $topUpAmount);
    }
}