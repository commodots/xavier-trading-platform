<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseReportService
{
    public function generate(Request $request): array
    {
        $filters = $this->parseFilters($request);
        return [
            'summary' => $this->summary($filters),
            'chart' => $this->chart($filters),
            'categories' => $this->categories($filters),
        ];
    }

    public function summary(array $filters): array
    {
        try {
            $query = Expense::query();
            $this->applyDateFilter($query, $filters);

            $totalExpenses = (float) (clone $query)->where('status', '!=', 'cancelled')->sum('amount');
            
            $largestCategory = null;
            try {
                $largestCategory = ExpenseCategory::with(['expenses' => function ($q) use ($filters) {
                    $this->applyDateFilter($q, $filters);
                }])->get()->sortByDesc(fn($cat) => $cat->expenses->where('status', '!=', 'cancelled')->sum('amount'))->first();
            } catch (\Exception $e) {
                $largestCategory = null;
            }

            $outstanding = (float) (clone $query)->whereIn('status', ['draft', 'approved'])->sum('amount');
            $averageMonthly = $this->averageMonthly($filters);

            $today = Expense::whereDate('expense_date', today())->where('status', '!=', 'cancelled')->sum('amount');
            $thisMonth = Expense::whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->where('status', '!=', 'cancelled')
                ->sum('amount');

            return [
                'total' => $totalExpenses,
                'today' => (float) $today,
                'month' => (float) $thisMonth,
                'largest_category' => $largestCategory ? [
                    'name' => $largestCategory->name,
                    'amount' => (float) $largestCategory->expenses->where('status', '!=', 'cancelled')->sum('amount'),
                ] : null,
                'outstanding' => $outstanding,
                'average_monthly' => $averageMonthly,
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'today' => 0,
                'month' => 0,
                'largest_category' => null,
                'outstanding' => 0,
                'average_monthly' => 0,
            ];
        }
    }

    public function chart(array $filters): array
    {
        try {
            $categories = ExpenseCategory::with(['expenses' => function ($q) use ($filters) {
                $this->applyDateFilter($q, $filters);
            }])->get();

            return [
                'categories' => $categories->pluck('name')->toArray(),
                'series' => [
                    [
                        'name' => 'Expenses',
                        'data' => $categories->map(fn($c) => (float) $c->expenses->sum('amount'))->toArray(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'categories' => [],
                'series' => [],
            ];
        }
    }

    public function categories(array $filters): array
    {
        try {
            $query = Expense::with(['category', 'vendor']);
            $this->applyDateFilter($query, $filters);

            return $query->latest()->get()->map(fn($e) => [
                'id' => $e->id,
                'expense_no' => $e->expense_no,
                'date' => $e->expense_date?->format('Y-m-d'),
                'category' => $e->category?->name ?? 'N/A',
                'vendor' => $e->vendor?->name ?? 'N/A',
                'amount' => (float) $e->amount,
                'status' => $e->status ?? 'draft',
            ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function averageMonthly(array $filters): float
    {
        $query = Expense::query();
        $this->applyDateFilter($query, $filters);

        $total = (float) $query->sum('amount');
        $months = 1;

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date']);
            $end = Carbon::parse($filters['end_date']);
            $months = max(1, $start->diffInMonths($end) + 1);
        }

        return $months > 0 ? round($total / $months, 2) : 0;
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
        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $period = $filters['period'] ?? 'month';
            match ($period) {
                'today' => $query->whereDate('expense_date', Carbon::today()),
                'week' => $query->whereDate('expense_date', '>=', Carbon::now()->subWeek()),
                'month' => $query->whereMonth('expense_date', Carbon::now()->month)
                    ->whereYear('expense_date', Carbon::now()->year),
                'year' => $query->whereYear('expense_date', Carbon::now()->year),
                default => null,
            };
        }
    }
}