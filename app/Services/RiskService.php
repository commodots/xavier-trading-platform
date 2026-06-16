<?php

namespace App\Services;

use App\Models\RiskFlag;
use App\Models\User;

class RiskService
{
    /**
     * Flag a user with a risk indicator.
     */
    public function flag(User $user, string $type, array $meta = []): RiskFlag
    {
        return RiskFlag::create([
            'user_id' => $user->id,
            'type' => $type,
            'severity' => $meta['severity'] ?? 'medium',
            'meta' => $meta,
        ]);
    }

    /**
     * Get all unresolved flags.
     */
    public function getActiveFlags()
    {
        return RiskFlag::with('user')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get flags for a specific user.
     */
    public function getUserFlags(User $user)
    {
        return $user->riskFlags()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Auto-flag: Multiple KYC failures
     */
    public function checkMultipleKycFailures(User $user): void
    {
        $failures = $user->kyc()
            ->where('status', 'rejected')
            ->count();

        if ($failures >= 2) {
            $existing = RiskFlag::where('user_id', $user->id)
                ->where('type', RiskFlag::TYPE_MULTIPLE_KYC_FAILURES)
                ->first();

            if (!$existing) {
                $this->flag($user, RiskFlag::TYPE_MULTIPLE_KYC_FAILURES, [
                    'failure_count' => $failures,
                    'severity' => 'high',
                ]);
            }
        }
    }

    /**
     * Auto-flag: Suspicious withdrawal
     */
    public function flagSuspiciousWithdrawal(User $user, array $meta = []): void
    {
        $this->flag($user, RiskFlag::TYPE_SUSPICIOUS_WITHDRAWAL, array_merge([
            'severity' => 'high',
        ], $meta));
    }

    /**
     * Auto-flag: High debt
     */
    public function checkHighDebt(User $user): void
    {
        $debt = (float) $user->wallet_debt;
        if ($debt > 0) {
            $existing = RiskFlag::where('user_id', $user->id)
                ->where('type', RiskFlag::TYPE_HIGH_DEBT)
                ->first();

            if (!$existing) {
                $this->flag($user, RiskFlag::TYPE_HIGH_DEBT, [
                    'debt_amount' => $debt,
                    'severity' => $debt > 100000 ? 'critical' : 'medium',
                ]);
            }
        }
    }

    /**
     * Auto-flag: Multiple devices
     */
    public function checkMultipleDevices(User $user): void
    {
        $deviceCount = $user->devices()->count();

        if ($deviceCount > 3) {
            $existing = RiskFlag::where('user_id', $user->id)
                ->where('type', RiskFlag::TYPE_MULTIPLE_DEVICES)
                ->first();

            if (!$existing) {
                $this->flag($user, RiskFlag::TYPE_MULTIPLE_DEVICES, [
                    'device_count' => $deviceCount,
                    'severity' => 'medium',
                ]);
            }
        }
    }

    /**
     * Dismiss / resolve a risk flag.
     */
    public function dismiss(RiskFlag $flag): void
    {
        $flag->delete();
    }
}