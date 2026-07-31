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
        $query = Expense::query();
        $this->applyDateFilter($query, $filters);

        $totalExpenses = (float) (clone $query)->sum('amount');
        $largestCategory = ExpenseCategory::with(['expenses' => function ($q) use ($filters) {
            $this->applyDateFilter($q, $filters);
        }])->get()->sortByDesc(fn($cat) => $cat->expenses->sum('amount'))->first();

        $outstanding = (float) (clone $query)->where('status', 'unpaid')->sum('amount');
        $averageMonthly = $this->averageMonthly($filters);

        return [
            'total' => $totalExpenses,
            'largest_category' => $largestCategory ? [
                'name' => $largestCategory->name,
                'amount' => (float) $largestCategory->expenses->sum('amount'),
            ] : null,
            'outstanding' => $outstanding,
            'average_monthly' => $averageMonthly,
        ];
    }

    public function chart(array $filters): array
    {
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
    }

    public function categories(array $filters): array
    {
        $query = Expense::with('category');
        $this->applyDateFilter($query, $filters);

        return $query->latest()->get()->map(fn($e) => [
            'id' => $e->id,
            'date' => $e->created_at?->format('Y-m-d'),
            'category' => $e->category?->name ?? 'N/A',
            'vendor' => $e->vendor ?? 'N/A',
            'amount' => (float) $e->amount,
            'status' => $e->status ?? 'paid',
        ])->toArray();
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
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $period = $filters['period'] ?? 'month';
            match ($period) {
                'today' => $query->whereDate('created_at', Carbon::today()),
                'week' => $query->whereDate('created_at', '>=', Carbon::now()->subWeek()),
                'month' => $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year),
                'year' => $query->whereYear('created_at', Carbon::now()->year),
                default => null,
            };
        }
    }
}