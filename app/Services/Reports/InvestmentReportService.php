<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = Order::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->monthlyChart(Order::class, 'created_at', 'id', 'count', 'Monthly Investment Volume'),
                $this->statusChart(Order::class, 'status', 'Investment Status'),
                $this->planChart(),
            ],
            $request->all()
        );
    }

    /**
     * Dashboard Summary
     */
    public function summary(): array
    {
        return [
            'total_investments' => Order::count(),
            'active' => Order::whereIn('status', ['open', 'pending'])->count(),
            'completed' => Order::where('status', 'filled')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'principal' => Order::sum(DB::raw('amount * price')),
            'expected_roi' => 0,
            'paid_roi' => 0,
        ];
    }

    /**
     * List investments (backward compatibility with existing controller).
     */
    public function list(array $filters = []): array
    {
        $query = Order::with('user:id,name,email');

        $builder = new ReportQueryBuilder($query);
        $builder->setAllowedSorts(['created_at', 'amount', 'status', 'market'])
            ->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null)
            ->applySearch($filters['search'] ?? null, ['symbol', 'market']);

        if (! empty($filters['plan'])) {
            $query->where('market', $filters['plan']);
        }
        if (! empty($filters['user'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['user']}%");
            });
        }
        if (! empty($filters['amount_min'])) {
            $query->where(DB::raw('amount * price'), '>=', $filters['amount_min']);
        }
        if (! empty($filters['amount_max'])) {
            $query->where(DB::raw('amount * price'), '<=', $filters['amount_max']);
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn ($o) => [
                'id' => $o->id,
                'investor' => $o->user?->name ?? 'N/A',
                'plan' => $o->market ?? $o->symbol ?? 'N/A',
                'amount' => $o->amount ?? ($o->price * $o->quantity),
                'roi' => 0,
                'start_date' => $o->created_at?->format('Y-m-d'),
                'maturity' => null,
                'status' => $o->status,
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * Filters 
     */
    public function filters(): array
    {
        return [
            'plans' => Order::select('market')->distinct()->whereNotNull('market')->orderBy('market')->pluck('market'),
            'statuses' => ['open', 'pending', 'filled', 'cancelled'],
        ];
    }

    /**
     * Top investors 
     */
    public function topInvestors(): array
    {
        return User::select('id', 'name', 'email')
            ->withCount('orders')
            ->orderBy('orders_count', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($u) => [
                'name' => $u->name,
                'email' => $u->email,
                'total_investments' => $u->orders_count,
            ])
            ->toArray();
    }

    /**
     * Distribution 
     */
    public function distribution(): array
    {
        return Order::select('market', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($o) => [
                'plan' => $o->market,
                'count' => $o->count,
                'total' => $o->total ?? 0,
            ])
            ->toArray();
    }

    /**
     * Charts 
     */
    public function charts(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $orders = Order::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $dates = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = now()->subDays($i)->format('M d');
            $data[] = (int) ($orders[$date] ?? 0);
        }

        return [
            'growth' => ['categories' => $dates, 'series' => [['name' => 'Investments', 'data' => $data]]],
            'distribution' => $this->distribution(),
        ];
    }

    /**
     * Investment Plans Chart
     */
    protected function planChart(): array
    {
        $rows = Order::select('market', DB::raw('COUNT(*) as total'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->orderBy('total', 'desc')
            ->get();

        return $this->chart(
            'Investment Plans',
            $rows->pluck('market')->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * Export
     */
    public function export(Request $request)
    {
        $query = Order::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request);

        return $this->exportCollection($query->get());
    }

    /**
     * Search
     */
    protected function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->whereHas('user', function ($user) use ($search) {
                $user->where('name', 'like', "%{$search}%");
            })
                ->orWhere('symbol', 'like', "%{$search}%")
                ->orWhere('market', 'like', "%{$search}%");
        });
    }
}
