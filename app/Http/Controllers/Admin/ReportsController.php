<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\ReportHistory;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FixedIncome\FixedIncomeReportService;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\DashboardService;
use App\Services\Reports\ExecutiveDashboardService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\FinancialSummaryReportService;
use App\Services\Reports\InvestmentPlanReportService;
use App\Services\Reports\InvestmentReportService;
use App\Services\Reports\KYCReportService;
use App\Services\Reports\LoginHistoryReportService;
use App\Services\Reports\MaturityReportService;
use App\Services\Reports\ProfitLossReportService;
use App\Services\Reports\ProfitLossService;
use App\Services\Reports\ReferralReportService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\ROIReportService;
use App\Services\Reports\SubscriptionReportService;
use App\Services\Reports\SystemReportService;
use App\Services\Reports\UserReportService;
use App\Services\Reports\WalletReportService;
use App\Services\Reports\WithdrawalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    protected DashboardReportService $dashboard;

    protected DashboardService $execDashboard;

    protected UserReportService $userReport;

    protected FinancialReportService $financial;

    protected InvestmentReportService $investment;

    protected WalletReportService $wallet;

    protected WithdrawalReportService $withdrawal;

    protected ReferralReportService $referral;

    protected SubscriptionReportService $subscription;

    protected SystemReportService $system;

    protected ReportExportService $export;

    protected RevenueReportService $revenueReport;

    protected ProfitLossService $profitLoss;

    protected ExpenseReportService $expenseReport;

    public function __construct(
        DashboardReportService $dashboard,
        DashboardService $execDashboard,
        UserReportService $userReport,
        FinancialReportService $financial,
        InvestmentReportService $investment,
        WalletReportService $wallet,
        WithdrawalReportService $withdrawal,
        ReferralReportService $referral,
        SubscriptionReportService $subscription,
        SystemReportService $system,
        ReportExportService $export,
        RevenueReportService $revenueReport,
        ProfitLossService $profitLoss,
        ExpenseReportService $expenseReport
    ) {
        $this->dashboard = $dashboard;
        $this->execDashboard = $execDashboard;
        $this->userReport = $userReport;
        $this->financial = $financial;
        $this->investment = $investment;
        $this->wallet = $wallet;
        $this->withdrawal = $withdrawal;
        $this->referral = $referral;
        $this->subscription = $subscription;
        $this->system = $system;
        $this->export = $export;
        $this->revenueReport = $revenueReport;
        $this->profitLoss = $profitLoss;
        $this->expenseReport = $expenseReport;
    }

    // Dashboard
    public function dashboard()
    {
        return response()->json($this->dashboard->all());
    }

    // Users
    public function users(Request $request)
    {
        if ($request->export) {
            return $this->exportUsers($request);
        }

        return response()->json($this->userReport->list($request->all()));
    }

    public function userSummary()
    {
        return response()->json($this->userReport->summary());
    }

    public function userFilters()
    {
        return response()->json($this->userReport->filters());
    }

    public function userDetail($id)
    {
        $user = $this->userReport->getUserById($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    protected function exportUsers(Request $request)
    {
        $data = $this->userReport->export($request->all());
        $headers = ['Name', 'Email', 'Phone', 'Country', 'KYC Status', 'Status', 'Joined', 'Last Login'];
        $rows = array_map(fn ($u) => [
            $u['name'], $u['email'], $u['phone'] ?? 'N/A',
            $u['country'] ?? 'N/A', $u['kyc_status'] ?? 'N/A',
            $u['status'] ?? 'N/A', $u['joined'] ?? 'N/A',
            $u['last_login'] ?? 'N/A',
        ], $data);

        if ($request->export === 'csv') {
            return $this->export->csv($rows, $headers, 'users-report');
        }

        return $this->export->excel($rows, $headers, 'users-report');
    }

    // Financial
    public function financial(Request $request)
    {
        $tab = $request->tab ?? 'deposits';
        $filters = $this->normalizeReportFilters($request);

        $data = match ($tab) {
            'deposits' => $this->financial->deposits($filters),
            'withdrawals' => $this->financial->withdrawals($filters),
            'wallet_transactions' => $this->financial->walletTransactions($filters),
            'fees' => $this->financial->fees($filters),
            'revenue' => $this->financial->revenue($filters),
            default => $this->financial->deposits($filters),
        };

        $data['charts'] = $this->financial->charts($tab, $filters);

        return response()->json($data);
    }

    public function financialSummary()
    {
        return response()->json($this->financial->summary());
    }

    public function financialStatistics(Request $request)
    {
        $type = $request->get('type', 'deposits');

        return response()->json($this->financial->getStatistics($type));
    }

    // Investments
    public function investments(Request $request)
    {
        return response()->json($this->investment->list($this->normalizeReportFilters($request)));
    }

    public function investmentSummary()
    {
        return response()->json($this->investment->summary());
    }

    public function investmentCharts()
    {
        return response()->json($this->investment->charts());
    }

    public function topInvestors()
    {
        return response()->json($this->investment->topInvestors());
    }

    public function investmentDistribution()
    {
        return response()->json($this->investment->distribution());
    }

    // Investment & User Reports
    public function roi(Request $request)
    {
        return response()->json(
            app(ROIReportService::class)->generate($request)
        );
    }

    public function maturity(Request $request)
    {
        return response()->json(
            app(MaturityReportService::class)->generate($request)
        );
    }

    public function investmentPlans(Request $request)
    {
        return response()->json(
            app(InvestmentPlanReportService::class)->generate($request)
        );
    }

    public function kyc(Request $request)
    {
        return response()->json(
            app(KYCReportService::class)->generate($request)
        );
    }

    public function subscriptions(Request $request)
    {
        return response()->json(
            app(SubscriptionReportService::class)->generate($request)
        );
    }

    public function loginHistory(Request $request)
    {
        return response()->json(
            app(LoginHistoryReportService::class)->generate($request)
        );
    }

    // Wallet & Withdrawals
    public function walletWithdrawals(Request $request)
    {
        $tab = $request->tab ?? 'withdrawals';
        $filters = $this->normalizeReportFilters($request);

        return response()->json(match ($tab) {
            'withdrawals' => $this->withdrawal->list($filters),
            'wallet_ledger' => $this->wallet->ledger($filters),
            'adjustments' => $this->wallet->adjustments($filters),
            default => $this->withdrawal->list($filters),
        });
    }

    public function walletWithdrawalSummary()
    {
        return response()->json([
            'withdrawals' => $this->withdrawal->summary(),
            'wallet' => $this->wallet->summary(),
        ]);
    }

    // Referral & Subscription
    public function referralsSubscriptions(Request $request)
    {
        $tab = $request->tab ?? 'referrals';

        return response()->json(match ($tab) {
            'referrals' => $this->referral->list($request->all()),
            'subscriptions' => $this->subscription->list($request->all()),
            default => $this->referral->list($request->all()),
        });
    }

    public function referralSubscriptionSummary()
    {
        return response()->json([
            'referrals' => $this->referral->summary(),
            'subscriptions' => $this->subscription->summary(),
        ]);
    }

    // System
    public function system()
    {
        return response()->json($this->system->all());
    }

    public function systemLogs()
    {
        return response()->json($this->system->logs());
    }

    public function integrationHealth()
    {
        return response()->json($this->system->integrationHealth());
    }

    // Maintenance (Super Admin only)
    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return response()->json(['message' => 'Cache cleared successfully']);
    }

    public function optimize()
    {
        Artisan::call('optimize');

        return response()->json(['message' => 'Application optimized']);
    }

    public function queueRestart()
    {
        Artisan::call('queue:restart');

        return response()->json(['message' => 'Queue worker restart signal sent']);
    }

    public function runScheduler()
    {
        Artisan::call('schedule:run');

        return response()->json(['message' => 'Scheduler executed']);
    }

    public function viewLogs()
    {
        $logFile = storage_path('logs/laravel.log');
        if (! file_exists($logFile)) {
            return response()->json(['logs' => []]);
        }
        $lines = tail($logFile, 100);
        $logs = array_map(fn ($line) => ['message' => $line], $lines);

        return response()->json(['logs' => $logs]);
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('query', '');
        $users = User::query()
            ->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function investmentFilters()
    {
        return response()->json($this->investment->filters());
    }

    public function execDashboard()
    {
        return response()->json([
            'summary' => $this->execDashboard->summary(),
            'charts' => $this->execDashboard->charts(),
            'recentTransactions' => $this->execDashboard->latestTransactions(),
            'pendingApprovals' => $this->execDashboard->pendingApprovals(),
            'systemHealth' => $this->execDashboard->systemHealth(),
        ]);
    }

    public function revenue(Request $request)
    {
        return response()->json(
            app(RevenueReportService::class)->generate($request)
        );
    }

    public function profitLoss(Request $request)
    {
        return response()->json(
            app(ProfitLossReportService::class)->generate($request)
        );
    }

    public function financialSummaryReport(
        Request $request,
        FinancialSummaryReportService $service
    ) {
        return response()->json($service->generate($request));
    }

    public function executiveDashboard(
        Request $request,
        ExecutiveDashboardService $service
    ) {
        return response()->json($service->generate($request));
    }

    public function expenses(Request $request)
    {
        $data = app(ExpenseReportService::class)->generate($request);

        // Add filter dropdown data
        $data['categories'] = ExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $data['vendors'] = Vendor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $data['departments'] = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($data);
    }

    public function downloads()
    {
        $history = ReportHistory::with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json([
            'history' => $history,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,pdf',
            'report_type' => 'required|in:revenue,profit-loss,expenses,users,financial,investments,roi,maturity,investment-plans,subscriptions,kyc,login-history,referrals-subscriptions,wallet-withdrawals,financial-summary,executive-dashboard,system,fixed-income-investments',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'tab' => 'nullable|string',
        ]);

        if ($request->report_type === 'wallet-withdrawals') {
            $request->validate(['tab' => 'nullable|in:withdrawals,wallet_ledger,adjustments']);
        } elseif ($request->report_type === 'referrals-subscriptions') {
            $request->validate(['tab' => 'nullable|in:referrals,subscriptions']);
        } elseif ($request->report_type === 'financial') {
            $request->validate(['tab' => 'nullable|in:deposits,withdrawals,wallet_transactions,fees,revenue']);
        }

        $request->merge([
            'from' => $request->start_date,
            'to' => $request->end_date,
        ]);

        $reportType = $request->report_type;

        // Get data from appropriate service
        $data = match ($reportType) {
            'revenue' => $this->getRevenueExportData($request),
            'profit-loss' => $this->getProfitLossExportData($request),
            'expenses' => $this->getExpensesExportData($request),
            'users' => $this->getUsersExportData($request),
            'financial' => $this->getFinancialExportData($request),
            'investments' => $this->getInvestmentsExportData($request),
            'roi' => $this->getROIExportData($request),
            'maturity' => $this->getMaturityExportData($request),
            'investment-plans' => $this->getInvestmentPlansExportData($request),
            'subscriptions' => $this->getSubscriptionsExportData($request),
            'kyc' => $this->getKYCExportData($request),
            'login-history' => $this->getLoginHistoryExportData($request),
            'referrals-subscriptions' => $this->getReferralsSubscriptionsExportData($request),
            'wallet-withdrawals' => $this->getWalletWithdrawalsExportData($request),
            'financial-summary' => $this->getFinancialSummaryExportData($request),
            'executive-dashboard' => $this->getExecutiveDashboardExportData($request),
            'system' => $this->getSystemExportData(),
            'fixed-income-investments' => $this->getFixedIncomeExportData($request),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };

        $fileResponse = app(ReportExportService::class)->export(
            $request->format,
            $data,
            $reportType,
            $request->start_date ?? '',
            $request->end_date ?? ''
        );

        $historyStart = $request->start_date ?? '1900-01-01';
        $historyEnd = $request->end_date ?? '2099-12-31';

        // Log the export
        ReportHistory::create([
            'user_id' => Auth::id(),
            'name' => ucwords(str_replace('-', ' ', $reportType)).' Report',
            'type' => $reportType,
            'format' => $request->format,
            'period' => $request->start_date || $request->end_date ? 'custom' : 'all-time',
            'start_date' => $historyStart,
            'end_date' => $historyEnd,
            'status' => 'completed',
        ]);

        return $fileResponse;
    }

    protected function getRevenueExportData(Request $request): array
    {
        $request->merge(['per_page' => 10000]);
        $report = app(RevenueReportService::class)->generate($request);
        $table = $report['table'] ?? [];
        if (is_object($table) && method_exists($table, 'items')) {
            $table = $table->items();
        } elseif (is_array($table) && isset($table['data'])) {
            $table = $table['data'];
        }

        return [
            'headers' => ['Date', 'Source', 'Reference', 'Description', 'User', 'Amount', 'Status'],
            'rows' => array_map(fn ($row) => [
                $row['date'] ?? 'N/A',
                $row['source_label'] ?? $row['source'] ?? 'N/A',
                $row['reference'] ?? 'N/A',
                $row['description'] ?? 'N/A',
                $row['user_id'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['status'] ?? 'N/A',
            ], $table),
        ];
    }

    protected function getProfitLossExportData(Request $request): array
    {
        $report = app(ProfitLossReportService::class)->generate($request);

        return [
            'headers' => ['Month', 'Revenue', 'Expenses', 'Profit', 'Margin'],
            'rows' => array_map(fn ($row) => [
                $row['month'] ?? 'N/A',
                $row['revenue'] ?? 0,
                $row['expenses'] ?? 0,
                $row['profit'] ?? 0,
                ($row['margin'] ?? 0).'%',
            ], $report['table'] ?? []),
        ];
    }

    protected function getExecutiveDashboardExportData(Request $request): array
    {
        $report = app(ExecutiveDashboardService::class)->generate($request);
        $rows = [];

        foreach (['summary', 'business', 'operational'] as $section) {
            foreach ($report[$section] ?? [] as $metric => $value) {
                $rows[] = [ucwords(str_replace('_', ' ', $section)), ucwords(str_replace('_', ' ', $metric)), $value];
            }
        }

        foreach (['latest_users', 'latest_deposits', 'latest_withdrawals', 'latest_investments'] as $section) {
            foreach ($report[$section] ?? [] as $record) {
                $rows[] = [ucwords(str_replace('_', ' ', $section)), json_encode($record), ''];
            }
        }

        return ['headers' => ['Section', 'Metric / Record', 'Value'], 'rows' => $rows];
    }

    protected function getSystemExportData(): array
    {
        $report = $this->system->all();
        $rows = [];

        foreach ($report['cards'] ?? [] as $card) {
            $rows[] = ['System Metrics', $card['label'] ?? 'Metric', $card['value'] ?? 'N/A'];
        }
        foreach ($report['integrations'] ?? [] as $integration) {
            $rows[] = ['Integration Health', $integration['name'] ?? 'Integration', $integration['status'] ?? 'N/A'];
        }
        foreach ($report['logs'] ?? [] as $type => $entries) {
            foreach ($entries as $entry) {
                $rows[] = [ucwords(str_replace('_', ' ', $type)), $entry['message'] ?? 'N/A', $entry['time'] ?? ''];
            }
        }

        return ['headers' => ['Section', 'Item', 'Value / Time'], 'rows' => $rows];
    }

    protected function getFixedIncomeExportData(Request $request): array
    {
        $data = app(FixedIncomeReportService::class)
            ->investments(array_merge($request->only([
                'status', 'product_id', 'currency', 'execution_mode', 'provider',
            ]), [
                'date_from' => $request->start_date,
                'date_to' => $request->end_date,
            ]));

        return [
            'headers' => ['Reference', 'Investor', 'Product', 'Principal', 'Currency', 'Interest Rate', 'Expected Interest', 'Actual Interest', 'Expected Maturity Amount', 'Actual Maturity Amount', 'Status', 'Funding Method', 'Execution Mode', 'Provider', 'Investment Date', 'Maturity Date'],
            'rows' => $data->map(fn ($row) => [
                $row['reference'] ?? 'N/A',
                $row['investor'] ?? 'N/A',
                $row['product'] ?? 'N/A',
                $row['principal'] ?? 0,
                $row['currency'] ?? 'N/A',
                $row['interest_rate'] ?? 0,
                $row['expected_interest'] ?? 0,
                $row['actual_interest'] ?? 0,
                $row['expected_maturity_amount'] ?? 0,
                $row['actual_maturity_amount'] ?? 0,
                $row['status'] ?? 'N/A',
                $row['funding_method'] ?? 'N/A',
                $row['execution_mode'] ?? 'N/A',
                $row['provider'] ?? 'N/A',
                $row['investment_date'] ?? 'N/A',
                $row['maturity_date'] ?? 'N/A',
            ])->all(),
        ];
    }

    protected function getUsersExportData(Request $request): array
    {
        $params = $request->all();
        $data = app(UserReportService::class)->export($params);

        $headers = ['Name', 'Email', 'Phone', 'Country', 'KYC Status', 'Status', 'Joined', 'Last Login'];
        $rows = array_map(fn ($u) => [
            $u['name'], $u['email'], $u['phone'] ?? 'N/A',
            $u['country'] ?? 'N/A', $u['kyc_status'] ?? 'N/A',
            $u['status'] ?? 'N/A', $u['joined'] ?? 'N/A',
            $u['last_login'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getFinancialExportData(Request $request): array
    {
        $tab = $request->get('tab', 'deposits');
        $methodMap = [
            'deposits' => 'deposits',
            'withdrawals' => 'withdrawals',
            'wallet_transactions' => 'walletTransactions',
            'fees' => 'fees',
            'revenue' => 'revenue',
        ];
        $method = $methodMap[$tab] ?? 'deposits';
        $params = array_merge($request->all(), ['per_page' => 10000]);
        $result = app(FinancialReportService::class)->{$method}($params);

        // Handle paginated response - extract the data array
        $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;

        $headers = match ($tab) {
            'wallet_transactions' => ['Date', 'Reference', 'User', 'Type', 'Amount', 'Currency', 'Status'],
            'fees' => ['Date', 'Reference', 'User', 'Fee Type', 'Amount', 'Currency', 'Status'],
            'revenue' => ['Date', 'Source', 'Type', 'Amount', 'Currency', 'Status'],
            default => ['Date', 'Reference', 'User', 'Amount', 'Currency', 'Method', 'Status'],
        };

        $rows = match ($tab) {
            'wallet_transactions' => array_map(fn ($item) => [
                $item['created_at'] ?? 'N/A',
                $item['reference'] ?? 'N/A',
                $item['user'] ?? 'N/A',
                $item['type'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['currency'] ?? 'N/A',
                $item['status'] ?? 'N/A',
            ], $data),
            'fees' => array_map(fn ($item) => [
                $item['created_at'] ?? 'N/A',
                $item['reference'] ?? 'N/A',
                $item['user'] ?? 'N/A',
                $item['type'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['currency'] ?? 'N/A',
                $item['status'] ?? 'N/A',
            ], $data),
            'revenue' => array_map(fn ($item) => [
                $item['created_at'] ?? 'N/A',
                $item['source'] ?? 'N/A',
                $item['type'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['currency'] ?? 'N/A',
                $item['status'] ?? 'N/A',
            ], $data),
            default => array_map(fn ($item) => [
                $item['created_at'] ?? 'N/A',
                $item['reference'] ?? 'N/A',
                $item['user'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['currency'] ?? 'N/A',
                $item['method'] ?? 'N/A',
                $item['status'] ?? 'N/A',
            ], $data),
        };

        return ['headers' => $headers, 'rows' => $rows, 'tab' => $tab];
    }

    protected function getInvestmentsExportData(Request $request): array
    {
        $params = array_merge($request->all(), ['per_page' => 10000]);
        $result = app(InvestmentReportService::class)->list($params);
        $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;

        $headers = ['Investor', 'Plan', 'Amount', 'ROI', 'Start Date', 'Maturity', 'Status'];
        $rows = array_map(fn ($item) => [
            $item['investor'] ?? 'N/A',
            $item['plan'] ?? 'N/A',
            $item['amount'] ?? 0,
            $item['roi'] ?? '0%',
            $item['start_date'] ?? 'N/A',
            $item['maturity'] ?? 'N/A',
            $item['status'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getROIExportData(Request $request): array
    {
        $data = app(ROIReportService::class)->export($request);
        $headers = ['Reference', 'Investor', 'Product', 'Principal', 'Expected Interest', 'Actual Interest', 'Status', 'Investment Date', 'Maturity Date'];
        $rows = array_map(fn ($item) => [
            $item['reference'] ?? 'N/A',
            $item['user']['name'] ?? 'N/A',
            $item['product']['name'] ?? 'N/A',
            $item['principal_amount'] ?? 0,
            $item['expected_interest'] ?? 0,
            $item['actual_interest'] ?? 0,
            $item['status'] ?? 'N/A',
            $item['investment_date'] ?? 'N/A',
            $item['maturity_date'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getMaturityExportData(Request $request): array
    {
        $data = app(MaturityReportService::class)->export($request);
        $headers = ['Reference', 'Investor', 'Product', 'Principal', 'Expected Maturity Amount', 'Actual Maturity Amount', 'Status', 'Maturity Date'];
        $rows = array_map(fn ($item) => [
            $item['reference'] ?? 'N/A',
            $item['user']['name'] ?? 'N/A',
            $item['product']['name'] ?? 'N/A',
            $item['principal_amount'] ?? 0,
            $item['expected_maturity_amount'] ?? 0,
            $item['actual_maturity_amount'] ?? 0,
            $item['status'] ?? 'N/A',
            $item['maturity_date'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getInvestmentPlansExportData(Request $request): array
    {
        $data = app(InvestmentPlanReportService::class)->export($request);
        $headers = ['Plan', 'Investors', 'Total Principal', 'Average Principal', 'Expected Interest', 'Status'];
        $rows = array_map(fn ($item) => [
            $item['market'] ?? 'N/A',
            $item['total_investments'] ?? 0,
            $item['total_amount'] ?? 0,
            $item['average_investment'] ?? 0,
            $item['expected_roi'] ?? 0,
            $item['status'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getSubscriptionsExportData(Request $request): array
    {
        $data = app(SubscriptionReportService::class)->export($request);
        $headers = ['User', 'Plan', 'Started', 'Expires', 'Status'];
        $rows = array_map(fn ($item) => [
            $item['user']['name'] ?? 'N/A',
            $item['plan']['name'] ?? 'N/A',
            $item['starts_at'] ?? 'N/A',
            $item['expires_at'] ?? 'N/A',
            $item['status'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getKYCExportData(Request $request): array
    {
        $data = app(KYCReportService::class)->export($request);
        $headers = ['User', 'Document', 'Submitted', 'Status'];
        $rows = array_map(fn ($item) => [
            $item['user']['name'] ?? 'N/A',
            $item['id_type'] ?? $item['document'] ?? 'N/A',
            $item['created_at'] ?? 'N/A',
            $item['status'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getLoginHistoryExportData(Request $request): array
    {
        $data = app(LoginHistoryReportService::class)->export($request);
        $headers = ['User', 'IP', 'Device', 'Browser', 'Location', 'Time', 'Status'];
        $rows = array_map(fn ($item) => [
            $item['user']['name'] ?? 'N/A',
            $item['ip_address'] ?? 'N/A',
            $item['device'] ?? 'N/A',
            $item['browser'] ?? 'N/A',
            $item['location'] ?? 'N/A',
            $item['logged_in_at'] ?? 'N/A',
            $item['successful'] ? 'Success' : 'Failed',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getReferralsSubscriptionsExportData(Request $request): array
    {
        $tab = $request->get('tab', 'referrals');
        $params = array_merge($request->all(), ['per_page' => 10000]);

        if ($tab === 'referrals') {
            $result = app(ReferralReportService::class)->list($params);
            $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;
            $headers = ['Referrer', 'Invitee', 'Investment', 'Commission', 'Status', 'Date'];
            $rows = array_map(fn ($item) => [
                $item['referrer'] ?? 'N/A',
                $item['invitee'] ?? 'N/A',
                $item['investment'] ?? 0,
                $item['commission'] ?? 0,
                $item['status'] ?? 'N/A',
                $item['created_at'] ?? 'N/A',
            ], $data);
        } else {
            $result = app(SubscriptionReportService::class)->list($params);
            $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;
            $headers = ['User', 'Plan', 'Started', 'Expires', 'Auto Renew', 'Status'];
            $rows = array_map(fn ($item) => [
                $item['user'] ?? 'N/A',
                $item['plan'] ?? 'N/A',
                $item['started'] ?? 'N/A',
                $item['expires'] ?? 'N/A',
                ($item['auto_renew'] ?? false) ? 'Yes' : 'No',
                $item['status'] ?? 'N/A',
            ], $data);
        }

        return ['headers' => $headers, 'rows' => $rows, 'tab' => $tab];
    }

    protected function getWalletWithdrawalsExportData(Request $request): array
    {
        $tab = $request->get('tab', 'withdrawals');
        $params = array_merge($request->all(), ['per_page' => 10000]);
        $result = match ($tab) {
            'withdrawals' => $this->withdrawal->list($params),
            'wallet_ledger' => $this->wallet->ledger($params),
            'adjustments' => $this->wallet->adjustments($params),
        };
        $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;

        $headers = match ($tab) {
            'withdrawals' => ['User', 'Amount', 'Method', 'Status', 'Requested'],
            'wallet_ledger' => ['Type', 'User', 'Amount', 'Currency', 'Date'],
            'adjustments' => ['User', 'Amount', 'Type', 'Reason', 'Date'],
            default => ['Type', 'Amount', 'Currency', 'Date'],
        };

        $rows = match ($tab) {
            'withdrawals' => array_map(fn ($item) => [
                $item['user'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['method'] ?? 'N/A',
                $item['status'] ?? 'N/A',
                $item['created_at'] ?? 'N/A',
            ], $data),
            'wallet_ledger' => array_map(fn ($item) => [
                $item['type'] ?? 'N/A',
                $item['user'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['currency'] ?? 'USD',
                $item['created_at'] ?? 'N/A',
            ], $data),
            'adjustments' => array_map(fn ($item) => [
                $item['user'] ?? $item['user_id'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['type'] ?? 'N/A',
                $item['reason'] ?? $item['note'] ?? 'N/A',
                $item['created_at'] ?? 'N/A',
            ], $data),
            default => [],
        };

        return ['headers' => $headers, 'rows' => $rows, 'tab' => $tab];
    }

    protected function getFinancialSummaryExportData(Request $request): array
    {
        $data = app(FinancialSummaryReportService::class)->generate($request);

        $headers = ['Month', 'Revenue', 'Expenses', 'Net Profit', 'Margin'];
        $rows = array_map(fn ($item) => [
            $item['month'] ?? 'N/A',
            $item['revenue'] ?? 0,
            $item['expenses'] ?? 0,
            $item['profit'] ?? 0,
            ($item['margin'] ?? 0).'%',
        ], $data['table'] ?? []);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function normalizeReportFilters(Request $request): array
    {
        $filters = $request->all();
        $filters['from'] = $filters['from'] ?? $filters['start_date'] ?? null;
        $filters['to'] = $filters['to'] ?? $filters['end_date'] ?? null;

        return $filters;
    }

    protected function getExpensesExportData(Request $request): array
    {
        $request->merge(['per_page' => 10000]);
        $result = app(ExpenseReportService::class)->generate($request);

        // Extract table data from paginator
        $tableData = is_array($result['table']) && isset($result['table']['data'])
            ? $result['table']['data']
            : ($result['table'] ?? []);

        $headers = [
            'Expense No',
            'Expense Date',
            'Category',
            'Vendor',
            'Department',
            'Amount',
            'Payment Method',
            'Reference',
            'Invoice Number',
            'Status',
            'Requested By',
            'Description',
        ];

        $rows = array_map(function ($item) {
            return [
                $item['expense_no'] ?? 'N/A',
                $item['expense_date'] ?? 'N/A',
                $item['category'] ?? 'N/A',
                $item['vendor'] ?? 'N/A',
                $item['department'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['payment_method'] ?? 'N/A',
                $item['reference'] ?? 'N/A',
                $item['invoice_number'] ?? 'N/A',
                $item['status'] ?? 'N/A',
                $item['requester'] ?? 'N/A',
                $item['description'] ?? 'N/A',
            ];
        }, $tableData);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $result['summary'] ?? [],
            'charts' => $result['charts'] ?? [],
        ];
    }
}

if (! function_exists('tail')) {
    function tail($file, $lines = 100)
    {
        if (! file_exists($file) || ! is_readable($file)) {
            return [];
        }

        // Use file() to read lines into an array and return the last N lines.
        // This is simpler and avoids edge cases with fseek on small files.
        $linesArr = @file($file, FILE_IGNORE_NEW_LINES);
        if ($linesArr === false) {
            return [];
        }

        if ($lines <= 0) {
            return [];
        }

        $total = count($linesArr);
        if ($total === 0) {
            return [];
        }

        return array_slice($linesArr, max(0, $total - $lines), $lines);
    }
}
