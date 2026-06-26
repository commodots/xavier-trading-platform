<?php

namespace App\Services\Reports;

use App\Models\Transaction;

class DepositReportService
{
    public function generate($from, $to)
    {
        return Transaction::query()
            ->where('type', 'deposit')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(50);
    }
}