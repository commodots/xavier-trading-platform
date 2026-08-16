<?php

namespace App\Services\Reports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitLossReportService extends BaseReportService
{
    /** Expense statuses that count toward P&L.
     * Draft and cancelled expenses should not contribute to P&L.
     * Only approved + paid expenses are recognised.
     */
    protected array $recognisedExpenseStatuses = [
        'approved',
        'paid',
    ];

    public function generate(Request $request): array
    {
        $revenue = $this->getRevenue($request);
        $expenses = $this->getExpenses($request);
        $netProfit = $revenue - $expenses;
        $profitMargin = $revenue > 0
            ? ($netProfit / $revenue) * 100
            : 0;

        return $this->response(
            [
                'total_revenue' => round($revenue, 2),
                'total_expenses' => round($expenses, 2),
                'net_profit' => round($netProfit, 2),
                'profit_margin' => round($profitMargin, 2),
                'result' => $netProfit >= 0 ? 'profit' : 'loss',
            ],
            $this->monthlyBreakdown($request),
            [
                $this->revenueExpenseChart($request),
            ],
            $request->all()
        );
    }

    /**
     * Total expenses for the period (approved + paid only).
     */
    public function getExpenses(Request $request): float
    {
        $query = Expense::query();
        $this->applyExpenseFilters($query, $request);

        return (float) $query->sum('amount');
    }

    /**
     * Total revenue for the period.
     *
     */
    public function getRevenue(Request $request): float
    {
        return app(RevenueReportService::class)->total($request);
    }

    /**
     * Apply the shared expense filters (status + date range).
     */
    protected function applyExpenseFilters(Builder $query, Request $request): void
    {
        $query->whereIn('status', $this->recognisedExpenseStatuses);

        $from = $request->filled('date_from') ? $request->date_from : $request->get('start_date');
        $to = $request->filled('date_to') ? $request->date_to : $request->get('end_date');

        if ($from) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('expense_date', '<=', $to);
        }
    }

    /**
     * Monthly revenue & expense breakdown.
     */
    public function monthlyBreakdown(Request $request): array
    {
        $revenue = $this->monthlyRevenue($request);
        $expenses = $this->monthlyExpenses($request);

        $months = collect(array_unique(array_merge(
            array_keys($revenue),
            array_keys($expenses)
        )))->sort()->values();

        return $months->map(function ($month) use ($revenue, $expenses) {
            $income = (float) ($revenue[$month] ?? 0);
            $expense = (float) ($expenses[$month] ?? 0);

            return [
                'month' => $month,
                'revenue' => round($income, 2),
                'expenses' => round($expense, 2),
                'profit' => round($income - $expense, 2),
                'margin' => $income > 0
                    ? round((($income - $expense) / $income) * 100, 2)
                    : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Monthly revenue 
     */
    protected function monthlyRevenue(Request $request): array
    {
        return app(RevenueReportService::class)->monthlyTotals($request);
    }

    /**
     * Monthly expenses grouped by YYYY-MM.
     */
    protected function monthlyExpenses(Request $request): array
    {
        $query = Expense::query();
        $this->applyExpenseFilters($query, $request);

        return $query
            ->select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($value) => (float) $value)
            ->toArray();
    }

    /** Revenue vs Expenses vs Profit chart.
     */
    protected function revenueExpenseChart(Request $request): array
    {
        $rows = collect($this->monthlyBreakdown($request));

        return [
            'title' => 'Revenue vs Expenses',
            'labels' => $rows->pluck('month')->values()->toArray(),
            'series' => [
                [
                    'name' => 'Revenue',
                    'data' => $rows->pluck('revenue')->values()->toArray(),
                ],
                [
                    'name' => 'Expenses',
                    'data' => $rows->pluck('expenses')->values()->toArray(),
                ],
                [
                    'name' => 'Profit',
                    'data' => $rows->pluck('profit')->values()->toArray(),
                ],
            ],
        ];
    }
}