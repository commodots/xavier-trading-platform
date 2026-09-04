<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use Illuminate\Support\Facades\DB;

class FixedIncomeDashboardService
{
    public function summary(): array
    {
        $base = FixedIncomeInvestment::query();

        return [
            'total_invested' => (float) (clone $base)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->sum('principal_amount'),

            'active_investments' => (clone $base)
                ->where('status', 'active')
                ->count(),

            'pending_execution' => (clone $base)
                ->where('status', 'pending_execution')
                ->count(),

            'maturing' => (clone $base)
                ->where('status', 'maturing')
                ->count(),

            'matured' => (clone $base)
                ->whereIn('status', ['matured', 'redeemed'])
                ->count(),

            'total_expected_interest' => (float) (clone $base)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->sum('expected_interest'),

            'total_interest_paid' => (float) (clone $base)
                ->where('status', 'redeemed')
                ->sum('actual_interest'),

            'manual_pending' => (clone $base)
                ->where('execution_mode', 'manual')
                ->where('status', 'pending_execution')
                ->count(),

            'automated_pending' => (clone $base)
                ->where('execution_mode', 'automated')
                ->where('status', 'pending_execution')
                ->count(),

            'failed_executions' => (clone $base)
                ->where('status', 'failed')
                ->count(),
        ];
    }
}