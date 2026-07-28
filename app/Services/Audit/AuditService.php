<?php

namespace App\Services\Audit;

use App\Models\TransactionAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log an action with the Spatie-style simple interface
     * 
     * Usage: AuditService::log('Deposit Created', $deposit);
     *        AuditService::log('Withdrawal Approved', $withdrawal);
     *        AuditService::log('KYC Approved', $user);
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?string $description = null
    ): TransactionAudit {
        $entityType = $model ? class_basename($model) : 'system';
        $entityId = $model ? $model->getKey() : 0;
        
        return TransactionAudit::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description ?? $action,
            'old_values' => null,
            'new_values' => $model ? $model->toArray() : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log a security event
     */
    public static function logSecurityEvent(
        User $user,
        string $eventType,
        string $description,
        array $metadata = []
    ): void {
        self::log($eventType, $user, $description);
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
    ): void {
        self::log($transactionType, $user, "{$transactionType}: {$amount} {$currency}");
    }

    /**
     * Log authentication events
     */
    public static function logAuthenticationEvent(
        User $user,
        string $eventType,
        bool $successful = true,
        array $metadata = []
    ): void {
        self::log($eventType, $user, $successful ? 'Successful' : 'Failed');
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
    ): void {
        self::log('update_' . $settingName, $user, "Changed {$settingName}");
    }

    /**
     * Get audit logs for a user
     */
    public static function getLogsForUser(User $user, string $eventType = null, int $limit = 50)
    {
        $query = TransactionAudit::where('user_id', $user->id);

        if ($eventType) {
            $query->where('entity_type', $eventType);
        }

        return $query->latest()->limit($limit)->get();
    }
}