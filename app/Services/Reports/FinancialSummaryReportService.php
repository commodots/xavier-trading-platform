<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;

class FinancialSummaryReportService extends BaseReportService
{
    /**
     * Generate the financial summary report.
     *
    
     *
     * All figures come from the same services so reports reconcile.
     */
    public function generate(Request $request): array
    {
        $revenueService = app(RevenueReportService::class);
        $profitLossService = app(ProfitLossReportService::class);

        $revenueData = $revenueService->generate($request);
        $profitLossData = $profitLossService->generate($request);

        $summary = $profitLossData['summary'] ?? [];
        $monthly = $profitLossData['table'] ?? [];

        return $this->response(
            [
                'total_revenue' => $summary['total_revenue'] ?? 0,
                'total_expenses' => $summary['total_expenses'] ?? 0,
                'net_profit' => $summary['net_profit'] ?? 0,
                'profit_margin' => $summary['profit_margin'] ?? 0,
                'result' => $summary['result'] ?? 'profit',
            ],
            $monthly,
            [
                'revenue' => $revenueData['charts'][0] ?? null,
                'profit_loss' => $profitLossData['charts'][0] ?? null,
            ],
            $request->all()
        );
    }
}