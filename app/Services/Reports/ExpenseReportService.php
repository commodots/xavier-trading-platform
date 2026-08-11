<?php

namespace App\Services\Reports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseReportService extends BaseReportService
{
    public function generate(Request $request): array
    {
        $query = Expense::query()
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->leftJoin('vendors', 'expenses.vendor_id', '=', 'vendors.id')
            ->leftJoin('departments', 'expenses.department_id', '=', 'departments.id')
            ->leftJoin('users as requester_user', 'expenses.requested_by', '=', 'requester_user.id')
            ->select(
                'expenses.id',
                'expenses.expense_no',
                'expenses.expense_date',
                'expenses.amount',
                'expenses.payment_method',
                'expenses.reference',
                'expenses.invoice_number',
                'expenses.status',
                'expenses.description',
                DB::raw('COALESCE(expense_categories.name, "N/A") as category'),
                DB::raw('COALESCE(vendors.name, "N/A") as vendor'),
                DB::raw('COALESCE(departments.name, "N/A") as department'),
                DB::raw('COALESCE(requester_user.name, "N/A") as requester')
            );

        $this->applyFilters($query, $request, 'expense_date');
        $this->applySorting($query, $request, 'expense_date');

        // Get paginated results
        $paginator = $this->paginate($query, $request);
        
        // Build table response with pagination metadata
        $tableResponse = [
            'data' => $paginator->getCollection()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return $this->response(
            $this->summary($request),
            $tableResponse,
            [
                $this->monthlyTrend($request),
                $this->categoryChart($request),
                $this->vendorChart($request),
                $this->departmentChart($request),
                $this->statusChart($request),
            ],
            $request->all()
        );
    }

    /**
     * Summary cards.
     */
    public function summary(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $total = (clone $query)->sum('amount');
        $approved = (clone $query)->where('status', 'approved')->sum('amount');
        $paid = (clone $query)->where('status', 'paid')->sum('amount');
        $draft = (clone $query)->where('status', 'draft')->sum('amount');
        $cancelled = (clone $query)->where('status', 'cancelled')->sum('amount');

        return [
            'total_expenses' => (float) $total,
            'approved_expenses' => (float) $approved,
            'paid_expenses' => (float) $paid,
            'draft_expenses' => (float) $draft,
            'cancelled_expenses' => (float) $cancelled,
            'expense_count' => (clone $query)->count(),
        ];
    }

    /**
     * Monthly expense trend.
     */
    protected function monthlyTrend(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $rows = $query
            ->select(
                DB::raw($this->monthExpression() . ' as period'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'title' => 'Monthly Expenses',
            'labels' => $rows->pluck('period')->values()->toArray(),
            'series' => [
                ['name' => 'Amount', 'data' => $rows->pluck('total')->values()->toArray()],
            ],
        ];
    }

    /**
     * Expense by category.
     */
    protected function categoryChart(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $rows = $query
            ->join(
                'expense_categories',
                'expenses.expense_category_id',
                '=',
                'expense_categories.id'
            )
            ->select(
                'expense_categories.name',
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'title' => 'Expenses by Category',
            'labels' => $rows->pluck('name')->toArray(),
            'series' => [
                ['name' => 'Amount', 'data' => $rows->pluck('total')->toArray()],
            ],
        ];
    }

    /**
     * Expense by vendor.
     */
    protected function vendorChart(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $rows = $query
            ->leftJoin(
                'vendors',
                'expenses.vendor_id',
                '=',
                'vendors.id'
            )
            ->select(
                DB::raw("COALESCE(vendors.name, 'No Vendor') as name"),
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('total')
            ->get();

        return [
            'title' => 'Expenses by Vendor',
            'labels' => $rows->pluck('name')->toArray(),
            'series' => [
                ['name' => 'Amount', 'data' => $rows->pluck('total')->toArray()],
            ],
        ];
    }

    /**
     * Expense by department.
     */
    protected function departmentChart(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $rows = $query
            ->leftJoin(
                'departments',
                'expenses.department_id',
                '=',
                'departments.id'
            )
            ->select(
                DB::raw("COALESCE(departments.name, 'Unassigned') as name"),
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->get();

        return [
            'title' => 'Expenses by Department',
            'labels' => $rows->pluck('name')->toArray(),
            'series' => [
                ['name' => 'Amount', 'data' => $rows->pluck('total')->toArray()],
            ],
        ];
    }

    /**
     * Expense status.
     */
    protected function statusChart(Request $request): array
    {
        $query = Expense::query();
        $this->applyFilters($query, $request, 'expense_date');

        $rows = $query
            ->select(
                'status',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'title' => 'Expense Status',
            'labels' => $rows->pluck('status')->toArray(),
            'series' => [
                ['name' => 'Count', 'data' => $rows->pluck('total')->toArray()],
            ],
        ];
    }

    /**
     * Search.
     */
    protected function applySearch(
        Builder $query,
        string $search
    ): void {
        $query->where(function ($q) use ($search) {
            $q->where('expenses.expense_no', 'like', "%{$search}%")
                ->orWhere('expenses.invoice_number', 'like', "%{$search}%")
                ->orWhere('expenses.reference', 'like', "%{$search}%")
                ->orWhere('expenses.description', 'like', "%{$search}%")
                ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', "%{$search}%"))
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
        });
    }

    /**
     * Report filters.
     */
    protected function applyFilters(
        Builder $query,
        Request $request,
        string $dateColumn = 'expense_date'
    ): Builder {
        // Date range - support both parameter naming conventions
        $startDate = $request->filled('date_from') ? $request->date_from : ($request->filled('start_date') ? $request->start_date : null);
        $endDate = $request->filled('date_to') ? $request->date_to : ($request->filled('end_date') ? $request->end_date : null);
        
        if ($startDate) {
            $query->whereDate($dateColumn, '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate($dateColumn, '<=', $endDate);
        }

        // Category
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        // Vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Search
        if ($request->filled('search')) {
            $this->applySearch($query, $request->search);
        }

        return $query;
    }

    /**
     * Database-specific month expression.
     */
    protected function monthExpression(): string
    {
        return "DATE_FORMAT(expenses.expense_date, '%Y-%m')";
    }
}