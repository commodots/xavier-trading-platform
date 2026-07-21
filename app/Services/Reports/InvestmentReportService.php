<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvestmentReportService
{
    protected ReportCacheService $cache;

    public function __construct(ReportCacheService $cache)
    {
        $this->cache = $cache;
    }

    public function summary(): array
    {
        return $this->cache->remember('investment.summary', function () {
            return [
                ['label' => 'Total Investments', 'value' => Order::count(), 'icon' => 'chart-bar', 'color' => '#0047AB'],
                ['label' => 'Active', 'value' => Order::whereIn('status', ['open', 'pending'])->count(), 'icon' => 'trending-up', 'color' => '#10B981'],
                ['label' => 'Completed', 'value' => Order::where('status', 'filled')->count(), 'icon' => 'check', 'color' => '#8B5CF6'],
                ['label' => 'Cancelled', 'value' => Order::where('status', 'cancelled')->count(), 'icon' => 'x-circle', 'color' => '#EF4444'],
                ['label' => 'ROI Paid', 'value' => 0, 'icon' => 'dollar-sign', 'color' => '#10B981'],
                ['label' => 'Average Investment', 'value' => round(Order::avg(DB::raw('amount * price')), 2), 'prefix' => '$', 'icon' => 'bar-chart', 'color' => '#0047AB'],
            ];
        }, 300);
    }

    public function list(array $filters = []): array
    {
        $query = Order::with('user:id,name,email');

        $builder = new ReportQueryBuilder($query);
        $builder->setAllowedSorts(['created_at', 'amount', 'status', 'market'])
            ->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null)
            ->applySearch($filters['search'] ?? null, ['symbol', 'market']);

        if (!empty($filters['plan'])) {
            $query->where('market', $filters['plan']);
        }
        if (!empty($filters['user'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['user']}%");
            });
        }
        if (!empty($filters['amount_min'])) {
            $query->where(DB::raw('amount * price'), '>=', $filters['amount_min']);
        }
        if (!empty($filters['amount_max'])) {
            $query->where(DB::raw('amount * price'), '<=', $filters['amount_max']);
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($o) => [
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

    public function filters(): array
    {
        return [
            'plans' => Order::select('market')->distinct()->whereNotNull('market')->orderBy('market')->pluck('market'),
            'statuses' => ['open', 'pending', 'filled', 'cancelled'],
        ];
    }

    public function topInvestors(): array
    {
        return User::select('id', 'name', 'email')
            ->withCount('orders')
            ->orderBy('orders_count', 'desc')
            ->take(10)
            ->get()
            ->map(fn($u) => [
                'name' => $u->name,
                'email' => $u->email,
                'total_investments' => $u->orders_count,
            ])
            ->toArray();
    }

    public function distribution(): array
    {
        return Order::select('market', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get()
            ->map(fn($o) => [
                'plan' => $o->market,
                'count' => $o->count,
                'total' => $o->total ?? 0,
            ])
            ->toArray();
    }

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
}