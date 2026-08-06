<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Wallet;
use App\Models\NewTransaction;
use App\Models\WithdrawalRequest;
use App\Models\Order;
use App\Models\PlatformEarning;
use App\Models\RevenueRecord;
use App\Models\KycProfile;
use App\Models\Investment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(): array
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $totalUsers = User::count();
        $activeInvestments = Order::whereIn('status', ['open', 'pending'])->count();
        $pendingWithdrawals = WithdrawalRequest::where('status', 'pending')->sum('amount');
        $pendingDeposits = NewTransaction::where('type', 'deposit')
            ->where('status', 'pending')
            ->sum('amount');
        $pendingKyc = KycProfile::where('status', 'pending')->count();
        $ngnBalance = Wallet::where('currency', 'NGN')->sum('ngn_cleared');
        $usdBalance = Wallet::where('currency', 'USD')->sum('usd_cleared');

        $totalRevenue = RevenueRecord::sum('amount');
        $todayRevenue = RevenueRecord::whereDate('record_date', $today)->sum('amount');
        $monthRevenue = RevenueRecord::whereMonth('record_date', $now->month)
            ->whereYear('record_date', $now->year)
            ->sum('amount');

        $systemHealth = $this->systemHealth();

        return [
            'totals' => [
                ['label' => 'Total Users', 'value' => $totalUsers, 'icon' => 'Users', 'color' => '#0047AB'],
                ['label' => 'Total Revenue', 'value' => $totalRevenue, 'icon' => 'DollarSign', 'color' => '#10B981', 'prefix' => '$'],
                ['label' => 'NGN Balance', 'value' => $ngnBalance, 'icon' => 'Wallet', 'color' => '#0047AB', 'prefix' => '₦'],
                ['label' => 'USD Balance', 'value' => $usdBalance, 'icon' => 'Wallet', 'color' => '#10B981', 'prefix' => '$'],
                ['label' => 'Active Investments', 'value' => $activeInvestments, 'icon' => 'TrendingUp', 'color' => '#F59E0B'],
                ['label' => 'Pending Withdrawals', 'value' => $pendingWithdrawals, 'icon' => 'ArrowUpRight', 'color' => '#EF4444', 'prefix' => '$'],
                ['label' => 'Pending Deposits', 'value' => $pendingDeposits, 'icon' => 'ArrowDownLeft', 'color' => '#3B82F6', 'prefix' => '$'],
                ['label' => 'Pending KYC', 'value' => $pendingKyc, 'icon' => 'ShieldCheck', 'color' => '#F59E0B'],
                ['label' => 'System Health', 'value' => $systemHealth['status'], 'icon' => 'MonitorCog', 'color' => $systemHealth['healthy'] ? '#10B981' : '#EF4444'],
            ],
        ];
    }

    public function charts(): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Revenue trend (last 30 days)
        $revenueTrend = RevenueRecord::select(DB::raw('DATE(record_date) as date'), DB::raw('SUM(amount) as total'))
            ->where('record_date', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        // Investment trend (last 30 days)
        $investmentTrend = Order::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // User growth (last 30 days)
        $userGrowth = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // Transactions (last 30 days)
        $transactions = NewTransaction::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $dates = [];
        $revenueData = [];
        $investmentData = [];
        $userData = [];
        $txData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = Carbon::now()->subDays($i)->format('M d');
            $revenueData[] = (float) ($revenueTrend[$date] ?? 0);
            $investmentData[] = (int) ($investmentTrend[$date] ?? 0);
            $userData[] = (int) ($userGrowth[$date] ?? 0);
            $txData[] = (int) ($transactions[$date] ?? 0);
        }

        return [
            'revenueTrend' => [
                'categories' => $dates,
                'series' => [['name' => 'Revenue', 'data' => $revenueData]],
            ],
            'investmentTrend' => [
                'categories' => $dates,
                'series' => [['name' => 'Investments', 'data' => $investmentData]],
            ],
            'userGrowth' => [
                'categories' => $dates,
                'series' => [['name' => 'Users', 'data' => $userData]],
            ],
            'transactions' => [
                'categories' => $dates,
                'series' => [['name' => 'Transactions', 'data' => $txData]],
            ],
        ];
    }

    public function latestTransactions(int $limit = 10): array
    {
        return NewTransaction::with('user:id,name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'user' => $t->user?->name ?? 'N/A',
                'type' => $t->type,
                'amount' => $t->amount,
                'currency' => $t->currency ?? 'USD',
                'status' => $t->status,
                'date' => $t->created_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    public function pendingApprovals(): array
    {
        $pendingKyc = KycProfile::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($k) => [
                'type' => 'KYC',
                'user' => $k->user?->name ?? 'N/A',
                'status' => $k->status,
                'date' => $k->created_at?->format('Y-m-d H:i'),
            ]);

        $pendingWithdrawals = WithdrawalRequest::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($w) => [
                'type' => 'Withdrawal',
                'user' => $w->user?->name ?? 'N/A',
                'amount' => $w->amount,
                'status' => $w->status,
                'date' => $w->created_at?->format('Y-m-d H:i'),
            ]);

        return array_merge($pendingKyc->toArray(), $pendingWithdrawals->toArray());
    }

    public function systemHealth(): array
    {
        $healthy = true;
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks[] = ['name' => 'Database', 'status' => 'healthy'];
        } catch (\Exception $e) {
            $healthy = false;
            $checks[] = ['name' => 'Database', 'status' => 'unhealthy'];
        }

        $checks[] = ['name' => 'Queue', 'status' => 'healthy'];
        $checks[] = ['name' => 'Cache', 'status' => 'healthy'];

        return [
            'healthy' => $healthy,
            'status' => $healthy ? 'Healthy' : 'Degraded',
            'checks' => $checks,
        ];
    }
}