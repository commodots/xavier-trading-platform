<?php

namespace App\Services\Reports;

use App\Models\RevenueRecord;
use App\Models\PlatformEarning;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\NewTransaction;
use App\Models\TransactionCharge;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public function generate(Request $request): array
    {
        $filters = $this->parseFilters($request);
        return [
            'summary' => $this->summary($filters),
            'chart' => $this->chart($filters),
            'table' => $this->table($filters),
        ];
    }

    public function summary(array $filters): array
    {
        $query = RevenueRecord::query();
        $this->applyDateFilter($query, $filters);

        $totalRevenue = (clone $query)->sum('amount');

        $sources = [
            'Subscriptions' => $this->getRevenueBySource('subscription', $filters),
            'Investment Fees' => $this->getRevenueBySource('investment_fee', $filters),
            'Deposit Charges' => $this->getRevenueBySource('deposit_charge', $filters),
            'Withdrawal Charges' => $this->getRevenueBySource('withdrawal_charge', $filters),
            'Trading Fees' => $this->getRevenueBySource('trading_fee', $filters),
            'Other Income' => $this->getRevenueBySource('other', $filters),
        ];

        $today = RevenueRecord::whereDate('record_date', Carbon::today())->sum('amount');
        $month = RevenueRecord::whereMonth('record_date', Carbon::now()->month)
            ->whereYear('record_date', Carbon::now()->year)
            ->sum('amount');
        $year = RevenueRecord::whereYear('record_date', Carbon::now()->year)->sum('amount');

        return [
            'today' => $today,
            'month' => $month,
            'year' => $year,
            'total' => $totalRevenue,
            'sources' => $sources,
        ];
    }

    public function chart(array $filters): array
    {
        $query = RevenueRecord::select(
            DB::raw('DATE_FORMAT(record_date, "%Y-%m") as month'),
            DB::raw('SUM(amount) as total')
        );

        $this->applyDateFilter($query, $filters);
        $data = $query->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'categories' => $data->pluck('month')->toArray(),
            'series' => [
                ['name' => 'Revenue', 'data' => $data->pluck('total')->toArray()],
            ],
        ];
    }

    public function table(array $filters): array
    {
        $sources = [
            'Subscriptions' => $this->getRevenueBySource('subscription', $filters),
            'Investment Fees' => $this->getRevenueBySource('investment_fee', $filters),
            'Deposit Charges' => $this->getRevenueBySource('deposit_charge', $filters),
            'Withdrawal Charges' => $this->getRevenueBySource('withdrawal_charge', $filters),
            'Trading Fees' => $this->getRevenueBySource('trading_fee', $filters),
            'Other Income' => $this->getRevenueBySource('other', $filters),
        ];

        $total = array_sum($sources);
        $rows = [];

        foreach ($sources as $name => $amount) {
            $rows[] = [
                'source' => $name,
                'amount' => $amount,
                'percentage' => $total > 0 ? round(($amount / $total) * 100, 2) : 0,
            ];
        }

        return $rows;
    }

    protected function getRevenueBySource(string $source, array $filters): float
    {
        $query = RevenueRecord::where('source', $source);
        $this->applyDateFilter($query, $filters);
        return (float) $query->sum('amount');
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
            $query->whereDate('record_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('record_date', '<=', $filters['end_date']);
        }
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $period = $filters['period'] ?? 'month';
            match ($period) {
                'today' => $query->whereDate('record_date', Carbon::today()),
                'week' => $query->whereDate('record_date', '>=', Carbon::now()->subWeek()),
                'month' => $query->whereMonth('record_date', Carbon::now()->month)
                    ->whereYear('record_date', Carbon::now()->year),
                'year' => $query->whereYear('record_date', Carbon::now()->year),
                default => null,
            };
        }
    }
}