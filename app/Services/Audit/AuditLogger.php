<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log a security/financial event to both the audit file and audit_logs table.
     *
     * @param string $event   e.g. 'withdrawal_created', 'password_changed'
     * @param array  $data    Should include 'user_id', optionally 'entity', 'entity_id', 'ip', 'user_agent'
     */
    public static function log(string $event, array $data = []): void
    {
        // Persist to dedicated audit log file (90-day retention)
        Log::channel('audit')->info($event, $data);

        // Persist to audit_logs DB table for admin/compliance visibility
        try {
            AuditLog::create([
                'user_id'    => $data['user_id'] ?? null,
                'action'     => $event,
                'entity'     => $data['entity'] ?? null,
                'entity_id'  => $data['entity_id'] ?? null,
                'ip_address' => $data['ip'] ?? request()->ip(),
                'user_agent' => $data['user_agent'] ?? request()->userAgent(),
                'payload'    => array_diff_key($data, array_flip(['user_id', 'entity', 'entity_id', 'ip', 'user_agent'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditLogger DB write failed: ' . $e->getMessage());
        }
    }
}
