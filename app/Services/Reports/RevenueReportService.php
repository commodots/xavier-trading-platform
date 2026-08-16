<?php

namespace App\Services\Reports;

use App\Models\Fee;
use App\Models\PlatformEarning;
use App\Models\RevenueRecord;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueReportService extends BaseReportService
{
    /**
     * Recognized (received) statuses for revenue records.
     *
     * Only these statuses are counted as revenue. Everything else
     * (pending/failed/cancelled/reversed/refunded) is excluded.
     */
    protected array $recognizedStatuses = [
        'paid',
        'completed',
        'successful',
        'active',
        'approved',
    ];

    /**
     * Generate the revenue report.
     */
    public function generate(Request $request): array
    {
        $rows = $this->queryRows($request);

        // Paginate the normalized collection
        $paginator = $this->paginateCollection($rows, $request);

        return $this->response(
            $this->summary($rows, $request),
            $paginator,
            [
                $this->monthlyTrend($rows),
                $this->sourceChart($rows),
            ],
            $request->all()
        );
    }

    /**
     * All normalized revenue rows for the current filters.
     */
    protected function queryRows(Request $request): Collection
    {
        $rows = collect();

        if ($this->sourceAllowed($request, 'subscription')) {
            $rows = $rows->concat($this->subscriptionRows($request));
        }

        if ($this->sourceAllowed($request, 'platform_fee')) {
            $rows = $rows->concat($this->platformFeeRows($request));
        }

        if ($this->sourceAllowed($request, 'trading_fee')) {
            $rows = $rows->concat($this->tradingFeeRows($request));
        }

        if ($this->sourceAllowed($request, 'other')) {
            $rows = $rows->concat($this->otherIncomeRows($request));
        }

       
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $rows = $rows->filter(function ($row) use ($search) {
                return str_contains(strtolower($row['reference'] ?? ''), $search)
                    || str_contains(strtolower($row['description'] ?? ''), $search)
                    || str_contains(strtolower($row['user_id'] ?? ''), $search);
            })->values();
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Source queries (database-level aggregation, no Model::all())
    // ------------------------------------------------------------------

    /**
     * Subscription revenue from user_subscriptions × subscription_plans.
     */
    protected function subscriptionRows(Request $request): Collection
    {
        $query = UserSubscription::query()
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->join('users', 'user_subscriptions.user_id', '=', 'users.id')
            ->select(
                'user_subscriptions.id',
                'user_subscriptions.created_at as date',
                'users.name as user_name',
                'subscription_plans.name as plan_name',
                'subscription_plans.price as amount',
                'user_subscriptions.status'
            )
            ->whereIn('user_subscriptions.status', $this->recognizedStatuses);

        $this->applyDateRange($query, $request, 'user_subscriptions.created_at');

        return $query->get()->map(fn ($row) => $this->normalize(
            source: 'subscription',
            date: $row->date,
            reference: 'SUB-'.$row->id,
            description: $row->plan_name.' subscription',
            user_id: $row->user_name,
            amount: (float) $row->amount,
            status: $row->status
        ));
    }

    /**
     * Platform/investment fees from platform_earnings.
     */
    protected function platformFeeRows(Request $request): Collection
    {
        $query = PlatformEarning::query()
            ->leftJoin('new_transactions_table as t', 'platform_earnings.transaction_id', '=', 't.id')
            ->leftJoin('users', 't.user_id', '=', 'users.id')
            ->select(
                'platform_earnings.id',
                'platform_earnings.created_at as date',
                'users.name as user_name',
                'platform_earnings.source',
                DB::raw('COALESCE(NULLIF(platform_earnings.amount_ngn, 0), platform_earnings.amount, 0) as amount'),
                DB::raw('COALESCE(NULLIF(t.status, ""), "paid") as status')
            )
            ->whereIn(DB::raw('COALESCE(NULLIF(t.status, ""), "paid")'), $this->recognizedStatuses);

        $this->applyDateRange($query, $request, 'platform_earnings.created_at');

        return $query->get()->map(fn ($row) => $this->normalize(
            source: 'platform_fee',
            date: $row->date,
            reference: 'PF-'.$row->id,
            description: ucwords(str_replace('_', ' ', $row->source)).' fee',
            user_id: $row->user_name,
            amount: (float) $row->amount,
            status: $row->status
        ));
    }

    /**
     * Trading fees from fees table.
     */
    protected function tradingFeeRows(Request $request): Collection
    {
        $query = Fee::query()
            ->leftJoin('users', 'fees.user_id', '=', 'users.id')
            ->select(
                'fees.id',
                'fees.created_at as date',
                'users.name as user_name',
                'fees.type',
                'fees.amount',
                DB::raw('"paid" as status')
            );

        $this->applyDateRange($query, $request, 'fees.created_at');

        return $query->get()->map(fn ($row) => $this->normalize(
            source: 'trading_fee',
            date: $row->date,
            reference: 'TF-'.$row->id,
            description: 'Trading fee ('.($row->type ?: 'trade').')',
            user_id: $row->user_name,
            amount: (float) $row->amount,
            status: 'paid'
        ));
    }

    /**
     * Other income from the existing revenue_records ledger.
     *
     * Only included when the ledger actually contains data, to avoid
     * double-counting with the other sources.
     */
    protected function otherIncomeRows(Request $request): Collection
    {
        if (! $this->hasRevenueRecords()) {
            return collect();
        }

        $query = RevenueRecord::query()
            ->leftJoin('new_transactions_table as t', 'revenue_records.transaction_id', '=', 't.id')
            ->leftJoin('users', 't.user_id', '=', 'users.id')
            ->select(
                'revenue_records.id',
                'revenue_records.record_date as date',
                'users.name as user_name',
                'revenue_records.source',
                DB::raw('COALESCE(NULLIF(revenue_records.currency, "USD"), "NGN") as currency'),
                DB::raw('COALESCE(NULLIF(revenue_records.amount, 0), 0) as amount'),
                DB::raw('"paid" as status')
            );

        $this->applyDateRange($query, $request, 'revenue_records.record_date');

        return $query->get()->map(fn ($row) => $this->normalize(
            source: 'other',
            date: $row->date,
            reference: 'REV-'.$row->id,
            description: ucwords(str_replace('_', ' ', $row->source)).' income',
            user_id: $row->user_name,
            amount: (float) $row->amount,
            status: 'paid'
        ));
    }

    /**
     * Whether the revenue_records ledger is populated.
     */
    protected function hasRevenueRecords(): bool
    {
        return RevenueRecord::query()->exists();
    }


    protected function normalize(
        string $source,
        $date,
        string $reference,
        string $description,
        $user_id,
        float $amount,
        string $status
    ): array {
        return [
            'date' => $date ? Carbon::parse($date)->format('Y-m-d') : null,
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'reference' => $reference,
            'description' => $description,
            'user_id' => $user_id ?: 'N/A',
            'amount' => $amount,
            'status' => $status,
        ];
    }

    protected function sourceLabel(string $source): string
    {
        return match ($source) {
            'subscription' => 'Subscription',
            'platform_fee' => 'Platform Fee',
            'trading_fee' => 'Trading Fee',
            'other' => 'Other Income',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    }


    protected function summary(Collection $rows, Request $request): array
    {
        $total = (float) $rows->sum('amount');
        $count = $rows->count();

        $now = Carbon::now();
        $thisMonth = $rows->filter(fn ($r) => $r['date'] && Carbon::parse($r['date'])->isSameMonth($now))->sum('amount');
        $thisYear = $rows->filter(fn ($r) => $r['date'] && Carbon::parse($r['date'])->year === $now->year)->sum('amount');

        return [
            'total_revenue' => round($total, 2),
            'revenue_this_month' => round((float) $thisMonth, 2),
            'revenue_this_year' => round((float) $thisYear, 2),
            'transaction_count' => $count,
            'average_revenue' => $count > 0 ? round($total / $count, 2) : 0,
            'growth' => $this->growth($rows, $request),
        ];
    }

    /**
     * Revenue growth: current vs previous comparable period.
     */
    protected function growth(Collection $rows, Request $request): ?float
    {
        $period = $request->get('period', 'month');

        $currentStart = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
        $currentEnd = match ($period) {
            'today' => Carbon::today()->endOfDay(),
            'week' => Carbon::now()->endOfWeek(),
            'quarter' => Carbon::now()->endOfQuarter(),
            'year' => Carbon::now()->endOfYear(),
            default => Carbon::now()->endOfMonth(),
        };

        $previousStart = (clone $currentStart)->sub($this->periodUnit($period), 1);
        $previousEnd = (clone $currentEnd)->sub($this->periodUnit($period), 1);

        $current = (float) $rows->filter(fn ($r) => $r['date']
            && Carbon::parse($r['date'])->between($currentStart, $currentEnd))->sum('amount');
        $previous = (float) $rows->filter(fn ($r) => $r['date']
            && Carbon::parse($r['date'])->between($previousStart, $previousEnd))->sum('amount');

        if ((float) $previous == 0) {
            return null; // N/A
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    protected function periodUnit(string $period): string
    {
        return match ($period) {
            'today' => 'day',
            'week' => 'week',
            'quarter' => 'quarter',
            'year' => 'year',
            default => 'month',
        };
    }

    protected function monthlyTrend(Collection $rows): array
    {
        $grouped = $rows
            ->filter(fn ($r) => $r['date'])
            ->groupBy(fn ($r) => Carbon::parse($r['date'])->format('Y-m'))
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->sortKeys();

        return [
            'title' => 'Monthly Revenue',
            'labels' => $grouped->keys()->values()->toArray(),
            'series' => [[
                'name' => 'Revenue',
                'data' => $grouped->values()->map(fn ($v) => round((float) $v, 2))->toArray(),
            ]],
        ];
    }

    protected function sourceChart(Collection $rows): array
    {
        $grouped = $rows
            ->groupBy('source_label')
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->sortDesc();

        return [
            'title' => 'Revenue by Source',
            'labels' => $grouped->keys()->values()->toArray(),
            'series' => [[
                'name' => 'Revenue',
                'data' => $grouped->values()->map(fn ($v) => round((float) $v, 2))->toArray(),
            ]],
        ];
    }

    protected function sourceAllowed(Request $request, string $source): bool
    {
        $filter = $request->get('source');
        if (! $filter || $filter === 'all') {
            return true;
        }

        return $filter === $source;
    }

    protected function sourceFilter(Request $request): ?string
    {
        return $request->get('source');
    }

    protected function applyDateRange(Builder $query, Request $request, string $column): void
    {
        $from = $request->filled('date_from') ? $request->date_from : $request->get('start_date');
        $to = $request->filled('date_to') ? $request->date_to : $request->get('end_date');

        if ($from) {
            $query->where($column, '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $query->where($column, '<=', Carbon::parse($to)->endOfDay());
        }
    }

    protected function applySearch(Builder $query, ?string $search, array $columns = []): void
    {
        if (! $search || empty($columns)) {
            return;
        }

        $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $i => $column) {
                if ($i === 0) {
                    $q->where($column, 'like', "%{$search}%");
                } else {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
    }

    /**
     * Manual pagination over a collection.
     */
    protected function paginateCollection(Collection $rows, Request $request): array
    {
        $perPage = max(1, (int) $request->get('per_page', 20));
        $page = max(1, (int) $request->get('page', 1));

        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $items->toArray(),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }


    /**
     * Total revenue for the current filters.
     *
     */
    public function total(Request $request): float
    {
        return (float) $this->queryRows($request)->sum('amount');
    }

    /**
     * Revenue grouped by month (YYYY-MM) for the current filters.
     *
     */
    public function monthlyTotals(Request $request): array
    {
        return $this->queryRows($request)
            ->filter(fn ($r) => $r['date'])
            ->groupBy(fn ($r) => Carbon::parse($r['date'])->format('Y-m'))
            ->map(fn ($items) => (float) $items->sum('amount'))
            ->sortKeys()
            ->toArray();
    }
}
