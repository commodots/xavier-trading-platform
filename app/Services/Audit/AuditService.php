<?php

namespace App\Services\Audit;

use App\Models\TransactionAudit;
use Illuminate\Http\Request;

class AuditService
{
    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        $old = null,
        $new = null
    ) {
        TransactionAudit::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}