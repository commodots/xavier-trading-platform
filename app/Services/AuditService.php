<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    /**
     * Log a security event
     */
    public static function logSecurityEvent(
        User $user,
        string $eventType,
        string $description,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata, [
                'timestamp' => now(),
                'source' => 'security_event',
            ]),
        ]);
    }

    /**
     * Log a financial transaction
     */
    public static function logFinancialTransaction(
        User $user,
        string $transactionType,
        float $amount,
        string $currency,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'financial_transaction',
            'description' => "{$transactionType}: {$amount} {$currency}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata, [
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'currency' => $currency,
                'timestamp' => now(),
            ]),
        ]);
    }

    /**
     * Log authentication events
     */
    public static function logAuthenticationEvent(
        User $user,
        string $eventType,
        bool $successful = true,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'description' => $eventType . ($successful ? ' (Successful)' : ' (Failed)'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata, [
                'successful' => $successful,
                'timestamp' => now(),
            ]),
        ]);
    }

    /**
     * Log user settings changes
     */
    public static function logSettingsChange(
        User $user,
        string $settingName,
        mixed $oldValue,
        mixed $newValue,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'settings_change',
            'description' => "Changed {$settingName}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($metadata, [
                'setting_name' => $settingName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'timestamp' => now(),
            ]),
        ]);
    }

    /**
     * Get audit logs for a user
     */
    public static function getLogsForUser(User $user, string $eventType = null, int $limit = 50)
    {
        $query = AuditLog::where('user_id', $user->id);

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        return $query->latest()->limit($limit)->get();
    }
}
