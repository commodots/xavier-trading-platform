<?php

namespace App\Services\Reports;

use App\Models\RevenueRecord;
use App\Models\PlatformEarning;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitLossService
{
    protected array $incomeSources = [
        'subscription',
        'investment_fee',
        'trading_fee',
        'withdrawal_charge',
        'deposit_charge',
    ];

    public function generate(Request $request): array
    {
        $filters = $this->parseFilters($request);
        return [
            'income' => $this->income($filters),
            'expenses' => $this->expenses($filters),
            'profit' => $this->profit($filters),
            'chart' => $this->chart($filters),
        ];
    }

    public function income(array $filters): array
    {
        $query = RevenueRecord::query();
        $this->applyDateFilter($query, $filters);

        $total = (float) (clone $query)->sum('amount');
        $breakdown = [];

        foreach ($this->incomeSources as $source) {
            $amount = (float) (clone $query)->where('source', $source)->sum('amount');
            $breakdown[] = [
                'source' => ucwords(str_replace('_', ' ', $source)),
                'amount' => $amount,
                'percentage' => $total > 0 ? round(($amount / $total) * 100, 2) : 0,
            ];
        }

        return [
            'total' => $total,
            'breakdown' => $breakdown,
        ];
    }

    public function expenses(array $filters): array
    {
        // P&L recognises only approved + paid expenses. Drafts are unapproved
        // commitments and cancelled expenses are reversed; neither may affect
        // reported profit. This must stay in sync with ProfitLossReportService
        // so exported P&L numbers reconcile with the on-screen report.
        $query = Expense::query()->whereIn('status', ['approved', 'paid']);
        $this->applyDateFilter($query, $filters);

        $total = (float) (clone $query)->sum('amount');

        $byCurrency = (clone $query)
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (float) $total)
            ->toArray();

        $totalNgn = (float) ($byCurrency['NGN'] ?? 0);
        $totalUsd = (float) ($byCurrency['USD'] ?? 0);

        $breakdown = ExpenseCategory::with(['expenses' => function ($q) use ($filters) {
            $q->whereIn('status', ['approved', 'paid']);
            $this->applyDateFilter($q, $filters);
        }])->get()->map(fn($cat) => [
            'category' => $cat->name,
            'amount' => (float) $cat->expenses->sum('amount'),
            'currency' => $cat->expenses->first()?->currency ?? 'NGN',
            'percentage' => $total > 0 ? round(($cat->expenses->sum('amount') / $total) * 100, 2) : 0,
        ])->toArray();

        return [
            'total' => $total,
            'total_ngn' => $totalNgn,
            'total_usd' => $totalUsd,
            'by_currency' => $byCurrency,
            'breakdown' => $breakdown,
        ];
    }

    public function profit(array $filters): array
    {
        $income = $this->income($filters)['total'];
        $expenses = $this->expenses($filters);
        $usdExpenses = $expenses['total_usd'];
        $ngnExpenses = $expenses['total_ngn'];

        $netProfit = $income - $usdExpenses;
        $netLoss = 0;
        $netLossCurrency = 'USD';

        // If income can't cover USD expenses, the shortfall is a USD loss
        if ($netProfit < 0) {
            $netLoss = abs($netProfit);
            $netLossCurrency = 'USD';
        }

        // NGN expenses represent a loss when there's no NGN income to offset them
        if ($ngnExpenses > 0 && $income <= 0) {
            $netLoss = $ngnExpenses;
            $netLossCurrency = 'NGN';
        }

        $margin = $income > 0 ? round(($netProfit / $income) * 100, 2) : 0;

        return [
            'income' => $income,
            'expenses' => $expenses['total'],
            'expenses_usd' => $usdExpenses,
            'expenses_ngn' => $ngnExpenses,
            'net_profit' => $netProfit,
            'net_loss' => $netLoss,
            'net_loss_currency' => $netLossCurrency,
            'margin' => $margin,
        ];
    }

    public function chart(array $filters): array
    {
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $income = RevenueRecord::whereBetween('record_date', [$monthStart, $monthEnd])->sum('amount');
            $expenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
                ->whereIn('status', ['approved', 'paid'])
                ->sum('amount');

            $months[] = [
                'month' => $date->format('Y-m'),
                'income' => (float) $income,
                'expenses' => (float) $expenses,
                'profit' => (float) ($income - $expenses),
            ];
        }

        return [
            'categories' => array_column($months, 'month'),
            'series' => [
                ['name' => 'Income', 'data' => array_column($months, 'income')],
                ['name' => 'Expenses', 'data' => array_column($months, 'expenses')],
                ['name' => 'Profit', 'data' => array_column($months, 'profit')],
            ],
        ];
    }

    protected function parseFilters(Request $request): array
    {
        return [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'period' => $request->get('period', 'month'),
        ];
    }

    protected function applyDateFilter($query, array $filters): void
    {
        $dateField = $query->getModel() instanceof Expense ? 'expense_date' : 'record_date';

        if (!empty($filters['start_date'])) {
            $query->whereDate($dateField, '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate($dateField, '<=', $filters['end_date']);
        }
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $period = $filters['period'] ?? 'month';
            match ($period) {
                'today' => $query->whereDate($dateField, Carbon::today()),
                'week' => $query->whereDate($dateField, '>=', Carbon::now()->subWeek()),
                'month' => $query->whereMonth($dateField, Carbon::now()->month)
                    ->whereYear($dateField, Carbon::now()->year),
                'year' => $query->whereYear($dateField, Carbon::now()->year),
                default => null,
            };
        }
    }
}