<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log an action with payload
     */
    public static function log(string $action, array $payload = []): void
    {
        $reserved = ['user_id', 'ip'];
        $userId = $payload['user_id'] ?? null;
        $ip = $payload['ip'] ?? request()->ip();

        // Filter reserved keys from payload
        $cleanPayload = array_filter($payload, function ($key) use ($reserved) {
            return !in_array($key, $reserved, true);
        }, ARRAY_FILTER_USE_KEY);

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ip,
            'payload' => $cleanPayload,
        ]);

        // Also log to audit channel
        Log::channel('audit')->info($action, $payload);
    }
}