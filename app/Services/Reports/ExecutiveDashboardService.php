<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\KycProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExecutiveDashboardService extends BaseReportService
{
    /**
     * Generate the executive dashboard data.
     *
     *  
     */
    public function generate(Request $request): array
    {
        $financial = app(FinancialSummaryReportService::class)->generate($request);
        $dashboard = app(DashboardReportService::class);

        return [
            'summary' => $this->financialSummary($financial),
            'summary_cards' => $dashboard->summaryCards(),
            'business' => $this->businessCards(),
            'operational' => $this->operationalCards(),
            'charts' => array_merge($this->charts($request), $dashboard->charts()),
            'alerts' => $this->alerts(),
            'latest_users' => $dashboard->latestUsers(),
            'latest_deposits' => $dashboard->latestDeposits(),
            'latest_withdrawals' => $dashboard->latestWithdrawals(),
            'latest_investments' => $dashboard->latestInvestments(),
            'filters' => $request->all(),
        ];
    }

    /**
     * Financial KPI cards 
     */
    protected function financialSummary(array $financial): array
    {
        $summary = $financial['summary'] ?? [];

        return [
            'total_revenue' => $summary['total_revenue'] ?? 0,
            'total_expenses' => $summary['total_expenses'] ?? 0,
            'net_profit' => $summary['net_profit'] ?? 0,
            'profit_margin' => $summary['profit_margin'] ?? 0,
            'result' => $summary['result'] ?? 'profit',
        ];
    }

    /**
     * Business KPI cards.
     *
     * Uses database-level counts (no User::all()).
     */
    protected function businessCards(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_suspended', false)
                ->whereNotNull('email_verified_at')
                ->count(),
            'total_investments' => Order::count(),
            'investment_value' => (float) Order::sum(
                DB::raw('COALESCE(amount, 0) + COALESCE(price * quantity, 0)')
            ),
        ];
    }

    /**
     * Operational KPI cards.
     */
    protected function operationalCards(): array
    {
        return [
            'pending_kyc' => $this->pendingKycCount(),
            'maturing_investments' => $this->maturingInvestmentsCount(),
            'active_subscriptions' => $this->activeSubscriptionsCount(),
            'pending_expenses' => Expense::where('status', 'draft')->count(),
        ];
    }

    /**
     * Charts
     */
    protected function charts(Request $request): array
    {
        $profitLoss = app(ProfitLossReportService::class)->generate($request);

        $financeChart = $profitLoss['charts'][0] ?? null;
        $monthly = $profitLoss['table'] ?? [];

        $financialChart = [
            'title' => 'Revenue vs Expenses vs Profit',
            'labels' => collect($monthly)->pluck('month')->values()->toArray(),
            'series' => [
                [
                    'name' => 'Revenue',
                    'data' => collect($monthly)->pluck('revenue')->values()->toArray(),
                ],
                [
                    'name' => 'Expenses',
                    'data' => collect($monthly)->pluck('expenses')->values()->toArray(),
                ],
                [
                    'name' => 'Profit',
                    'data' => collect($monthly)->pluck('profit')->values()->toArray(),
                ],
            ],
        ];

        return [
            'financial' => $financialChart ?: $financeChart,
            'profit_loss' => $financeChart,
        ];
    }

    /**
     * Alerts / action items.
     */
    protected function alerts(): array
    {
        $pendingKyc = $this->pendingKycCount();
        $maturing = $this->maturingInvestmentsCount();
        $pendingExpenses = Expense::where('status', 'draft')->count();

        $items = [];

        if ($pendingKyc > 0) {
            $items[] = [
                'type' => 'kyc',
                'level' => 'warning',
                'message' => "{$pendingKyc} KYC application(s) pending review",
                'link' => '/admin/reports/kyc',
            ];
        }

        if ($maturing > 0) {
            $items[] = [
                'type' => 'investment',
                'level' => 'info',
                'message' => "{$maturing} investment(s) approaching maturity",
                'link' => '/admin/reports/maturity',
            ];
        }

        if ($pendingExpenses > 0) {
            $items[] = [
                'type' => 'expense',
                'level' => 'warning',
                'message' => "{$pendingExpenses} expense(s) awaiting processing",
                'link' => '/admin/reports/expenses',
            ];
        }

        return $items;
    }

    protected function pendingKycCount(): int
    {
        return KycProfile::where('status', 'pending')->count();
    }

    protected function maturingInvestmentsCount(): int
    {
        return Order::whereBetween('created_at', [
            Carbon::today(),
            Carbon::today()->addDays(30),
        ])->count();
    }

    protected function activeSubscriptionsCount(): int
    {
        return UserSubscription::where('status', 'active')
            ->where('expires_at', '>', Carbon::now())
            ->count();
    }
}