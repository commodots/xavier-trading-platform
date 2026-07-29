<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Models\Order;
use App\Models\PlatformEarning;
use App\Models\RevenueRecord;
use App\Models\KycProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardReportService
{
    protected ReportCacheService $cache;

    public function __construct(ReportCacheService $cache)
    {
        $this->cache = $cache;
    }

    public function summaryCards(): array
    {
        return $this->cache->remember('dashboard.summary', function () {
            $today = Carbon::today();
            $now = Carbon::now();

            // User metrics
            $totalUsers = User::count();
            $verifiedUsers = User::where('kyc_status', 'verified')->count();
            $pendingKyc = KycProfile::where('status', 'pending')->count();
            $suspendedUsers = User::where('is_suspended', true)->count();
            $premiumUsers = User::where('subscription_status', 'active')->count();

            // Wallet metrics
            $totalWalletBalance = Wallet::sum('balance');
            $totalNgnBalance = Wallet::where('currency', 'NGN')->sum('ngn_cleared');
            $totalUsdBalance = Wallet::where('currency', 'USD')->sum('usd_cleared');
            $todayDeposits = Transaction::where('type', 'deposit')
                ->whereDate('created_at', $today)->sum('amount');
            $todayWithdrawals = WithdrawalRequest::whereDate('created_at', $today)->sum('amount');
            $pendingWithdrawals = WithdrawalRequest::where('status', 'pending')->sum('amount');

            // Investment metrics (orders as proxy)
            $totalInvestments = Order::count();
            $activeInvestments = Order::whereIn('status', ['open', 'pending'])->count();
            $completedInvestments = Order::where('status', 'filled')->count();
            $cancelledInvestments = Order::where('status', 'cancelled')->count();

            // Revenue metrics
            $todayRevenue = PlatformEarning::whereDate('created_at', $today)->sum('amount');
            $monthlyRevenue = PlatformEarning::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)->sum('amount');
            $totalFees = PlatformEarning::sum('amount');
            $commissionsPaid = 0; // No commission model yet

            // Calculate trends (compare with previous period)
            $prevPeriodStart = Carbon::now()->subDays(30);

            return [
                'users' => [
                    ['title' => 'Total Users', 'value' => $totalUsers, 'icon' => 'users', 'color' => '#0047AB'],
                    ['title' => 'Verified Users', 'value' => $verifiedUsers, 'icon' => 'check-circle', 'color' => '#10B981'],
                    ['title' => 'Pending KYC', 'value' => $pendingKyc, 'icon' => 'clock', 'color' => '#F59E0B'],
                    ['title' => 'Suspended Users', 'value' => $suspendedUsers, 'icon' => 'ban', 'color' => '#EF4444'],
                    ['title' => 'Premium Users', 'value' => $premiumUsers, 'icon' => 'star', 'color' => '#8B5CF6'],
                ],
                'wallet' => [
                    ['title' => 'Total Wallet Balance (NGN)', 'value' => $totalNgnBalance, 'prefix' => '₦', 'icon' => 'dollar', 'color' => '#0047AB'],
                    ['title' => 'Total Wallet Balance (USD)', 'value' => $totalUsdBalance, 'prefix' => '$', 'icon' => 'dollar', 'color' => '#10B981'],
                    ['title' => "Today's Deposits", 'value' => $todayDeposits, 'prefix' => '$', 'icon' => 'trending-up', 'color' => '#10B981'],
                    ['title' => "Today's Withdrawals", 'value' => $todayWithdrawals, 'prefix' => '$', 'icon' => 'activity', 'color' => '#EF4444'],
                    ['title' => 'Pending Withdrawals', 'value' => $pendingWithdrawals, 'prefix' => '$', 'icon' => 'clock', 'color' => '#F59E0B'],
                ],
                'investments' => [
                    ['title' => 'Total Investments', 'value' => $totalInvestments, 'icon' => 'activity', 'color' => '#0047AB'],
                    ['title' => 'Active Investments', 'value' => $activeInvestments, 'icon' => 'trending-up', 'color' => '#10B981'],
                    ['title' => 'Completed Investments', 'value' => $completedInvestments, 'icon' => 'check-circle', 'color' => '#8B5CF6'],
                    ['title' => 'Cancelled Investments', 'value' => $cancelledInvestments, 'icon' => 'ban', 'color' => '#EF4444'],
                ],
                'revenue' => [
                    ['title' => "Today's Revenue", 'value' => $todayRevenue, 'prefix' => '$', 'icon' => 'trending-up', 'color' => '#10B981'],
                    ['title' => 'Monthly Revenue', 'value' => $monthlyRevenue, 'prefix' => '$', 'icon' => 'activity', 'color' => '#0047AB'],
                    ['title' => 'Total Fees', 'value' => $totalFees, 'prefix' => '$', 'icon' => 'dollar', 'color' => '#F59E0B'],
                    ['title' => 'Commissions Paid', 'value' => $commissionsPaid, 'prefix' => '$', 'icon' => 'shield', 'color' => '#8B5CF6'],
                ],
            ];
        });
    }

    public function charts(): array
    {
        return $this->cache->remember('dashboard.charts', function () {
            $now = Carbon::now();
            $thirtyDaysAgo = Carbon::now()->subDays(30);

            // Users registered (last 30 days)
            $userRegistrations = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date');

            // Deposits vs Withdrawals (last 30 days)
            $deposits = Transaction::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
                ->where('type', 'deposit')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');

            $withdrawals = WithdrawalRequest::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
                ->where('status', 'approved')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');

            // Investments (last 30 days)
            $investments = Order::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date');

            // Revenue (last 30 days)
            $revenue = PlatformEarning::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');

            // Build date series for the last 30 days
            $dates = [];
            $userData = [];
            $depositData = [];
            $withdrawalData = [];
            $investmentData = [];
            $revenueData = [];

            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $dates[] = Carbon::now()->subDays($i)->format('M d');
                $userData[] = (int) ($userRegistrations[$date] ?? 0);
                $depositData[] = (float) ($deposits[$date] ?? 0);
                $withdrawalData[] = (float) ($withdrawals[$date] ?? 0);
                $investmentData[] = (int) ($investments[$date] ?? 0);
                $revenueData[] = (float) ($revenue[$date] ?? 0);
            }

            return [
                'usersGrowth' => [
                    'categories' => $dates,
                    'series' => [
                        ['name' => 'Registered Users', 'data' => $userData],
                    ],
                ],
                'depositsVsWithdrawals' => [
                    'categories' => $dates,
                    'series' => [
                        ['name' => 'Deposits', 'data' => $depositData],
                        ['name' => 'Withdrawals', 'data' => $withdrawalData],
                    ],
                ],
                'investments' => [
                    'categories' => $dates,
                    'series' => [
                        ['name' => 'Investments', 'data' => $investmentData],
                    ],
                ],
                'revenue' => [
                    'categories' => $dates,
                    'series' => [
                        ['name' => 'Revenue', 'data' => $revenueData],
                    ],
                ],
            ];
        }, 300); // 5 minute cache
    }

    public function latestUsers(int $limit = 10): array
    {
        return User::with('kyc')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'country' => $u->country,
                'status' => $u->is_suspended ? 'suspended' : ($u->kyc_status === 'verified' ? 'active' : 'pending'),
                'joined' => $u->created_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    public function latestDeposits(int $limit = 10): array
    {
        return Transaction::with('user:id,name,email')
            ->where('type', 'deposit')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($t) => [
                'user' => $t->user?->name ?? 'N/A',
                'amount' => $t->amount,
                'method' => $t->asset ?? 'N/A',
                'status' => $t->status,
                'time' => $t->created_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    public function latestWithdrawals(int $limit = 10): array
    {
        return WithdrawalRequest::with('user:id,name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($w) => [
                'user' => $w->user?->name ?? 'N/A',
                'amount' => $w->amount,
                'status' => $w->status,
                'requested' => $w->created_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    public function latestInvestments(int $limit = 10): array
    {
        return Order::with('user:id,name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($o) => [
                'user' => $o->user?->name ?? 'N/A',
                'plan' => $o->market ?? $o->symbol ?? 'N/A',
                'amount' => $o->amount ?? ($o->price * $o->quantity),
                'status' => $o->status,
            ])
            ->toArray();
    }

    public function all(): array
    {
        return [
            'summary' => $this->summaryCards(),
            'charts' => $this->charts(),
            'latest_users' => $this->latestUsers(),
            'latest_deposits' => $this->latestDeposits(),
            'latest_withdrawals' => $this->latestWithdrawals(),
            'latest_investments' => $this->latestInvestments(),
        ];
    }
}