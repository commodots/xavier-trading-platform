<?php

namespace App\Services\Reports;

use App\Models\Transaction;

class WithdrawalReportService
{
    public function generate($from, $to)
    {
        return Transaction::query()
            ->with('user:id,name,email')
            ->where('type', 'withdrawal')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(50);
    }
}