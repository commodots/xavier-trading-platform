<?php

namespace App\Services\Reports;

use App\Models\TransactionAudit;

class AuditTrailService
{
    public function generate($from, $to)
    {
        return TransactionAudit::query()
            ->with('user')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(50);
    }
}