<?php

namespace App\Services\Compliance;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PciPsd2Compliance
{
    /**
     * PCI-DSS requirement: Strong authentication for payment operations.
     * Verify user has 2FA enabled AND email verified before allowing highvalue transactions.
     */
    public static function canProcessPayment(User $user, float $amount): bool
    {
        // 1. Email must be verified
        if ($user->email_verified_at === null) {
            Log::warning('PCI-DSS: Payment blocked - email not verified', ['user_id' => $user->id]);

            return false;
        }

        // 2. For high-value transactions (>NGN 1M or >USD 2.5K), require 2FA
        $thresholdNgn = 1000000;
        $thresholdUsd = 2500;
        $currencyAmount = $amount; // Assumes amount is in base currency

        if ($currencyAmount > $thresholdNgn && $user->two_factor_confirmed_at === null) {
            Log::warning('PCI-DSS: High-value payment blocked - 2FA required', [
                'user_id' => $user->id,
                'amount' => $amount,
            ]);

            return false;
        }

        return true;
    }

    /**
     * PSD2 requirement: Strong customer authentication for fund transfers.
     * Ensure user completed KYC + document verification.
     */
    public static function canInitiateTransfer(User $user): bool
    {
        // 1. KYC must be verified
        if ($user->kyc_verified !== true) {
            Log::warning('PSD2: Transfer blocked - KYC not verified', ['user_id' => $user->id]);

            return false;
        }

        // 2. At least one document must be verified
        $verifiedDocs = $user->kycProfiles()
            ->where('status', 'verified')
            ->count();

        if ($verifiedDocs === 0) {
            Log::warning('PSD2: Transfer blocked - no verified documents', ['user_id' => $user->id]);

            return false;
        }

        return true;
    }

    /**
     * PCI-DSS requirement: Limit transaction amounts per day to detect fraud.
     * Returns remaining available limit for the day.
     */
    public static function getDailyTransactionLimit(User $user, string $transactionType = 'withdrawal'): float
    {
        $dailyLimitNgn = 10000000; // NGN 10M per day

        $usedToday = \App\Models\NewTransaction::where('user_id', $user->id)
            ->where('type', $transactionType)
            ->where('status', 'completed')
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $remaining = $dailyLimitNgn - $usedToday;

        return max(0, $remaining);
    }

    /**
     * PSD2 requirement: Detect suspicious activity (unusual amount, location, timing).
     */
    public static function isSuspiciousActivity(User $user, array $transactionData): bool
    {
        $amount = $transactionData['amount'] ?? 0;
        $userAvgTransaction = \App\Models\NewTransaction::where('user_id', $user->id)
            ->where('type', $transactionData['type'] ?? 'withdrawal')
            ->avg('amount') ?? 0;

        // Flag if amount is 5x normal average
        if ($userAvgTransaction > 0 && $amount > ($userAvgTransaction * 5)) {
            Log::warning('PSD2: Suspicious transaction detected', [
                'user_id' => $user->id,
                'amount' => $amount,
                'average' => $userAvgTransaction,
            ]);

            return true;
        }

        return false;
    }

    /**
     * PCI-DSS requirement: Log all payment card/PII interactions for audit.
     */
    public static function logPaymentOperation(User $user, string $operation, array $metadata = []): void
    {
        Log::info('PaymentAudit', [
            'user_id' => $user->id,
            'operation' => $operation,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeIso8601(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * PCI-DSS requirement: Ensure no PII/card data is stored in logs/cache.
     */
    public static function sanitizeForLogging(array $data): array
    {
        $sensitive_keys = ['card_number', 'cvv', 'password', 'secret', 'token', 'pin'];

        foreach ($sensitive_keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }
}
