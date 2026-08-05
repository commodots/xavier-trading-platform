<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportHistory;
use App\Models\User;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\DashboardService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\InvestmentPlanReportService;
use App\Services\Reports\InvestmentReportService;
use App\Services\Reports\KYCReportService;
use App\Services\Reports\LoginHistoryReportService;
use App\Services\Reports\MaturityReportService;
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

        return response()->json(match ($tab) {
            'deposits' => $this->financial->deposits($request->all()),
            'withdrawals' => $this->financial->withdrawals($request->all()),
            'wallet_transactions' => $this->financial->walletTransactions($request->all()),
            'fees' => $this->financial->fees($request->all()),
            'revenue' => $this->financial->revenue($request->all()),
            default => $this->financial->deposits($request->all()),
        });
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
        return response()->json($this->investment->list($request->all()));
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

        return response()->json(match ($tab) {
            'withdrawals' => $this->withdrawal->list($request->all()),
            'wallet_ledger' => $this->wallet->ledger($request->all()),
            'adjustments' => $this->wallet->adjustments($request->all()),
            default => $this->withdrawal->list($request->all()),
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
            app(ProfitLossService::class)->generate($request)
        );
    }

    public function expenses(Request $request)
    {
        return response()->json(
            app(ExpenseReportService::class)->generate($request)
        );
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
            'report_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $reportType = $request->report_type;

        // Get data from appropriate service
        $data = match ($reportType) {
            'revenue' => app(RevenueReportService::class)->generate($request),
            'profit-loss' => app(ProfitLossService::class)->generate($request),
            'expenses' => app(ExpenseReportService::class)->generate($request),
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
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };

        // Log the export
        ReportHistory::create([
            'user_id' => Auth::id(),
            'name' => ucwords(str_replace('-', ' ', $reportType)).' Report',
            'type' => $reportType,
            'format' => $request->format,
            'period' => 'custom',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'completed',
        ]);

        return app(ReportExportService::class)->export(
            $request->format,
            $data,
            $reportType,
            $request->start_date,
            $request->end_date
        );
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
        $headers = ['Reference', 'Investor', 'Plan', 'Principal', 'Expected ROI', 'Status', 'Created'];
        $rows = array_map(fn ($item) => [
            $item['id'] ?? 'N/A',
            $item['user']['name'] ?? 'N/A',
            $item['market'] ?? $item['symbol'] ?? 'N/A',
            $item['amount'] ?? 0,
            '0%',
            $item['status'] ?? 'N/A',
            $item['created_at'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getMaturityExportData(Request $request): array
    {
        $data = app(MaturityReportService::class)->export($request);
        $headers = ['Reference', 'Investor', 'Plan', 'Principal', 'Status', 'Created'];
        $rows = array_map(fn ($item) => [
            $item['id'] ?? 'N/A',
            $item['user']['name'] ?? 'N/A',
            $item['market'] ?? $item['symbol'] ?? 'N/A',
            $item['amount'] ?? 0,
            $item['status'] ?? 'N/A',
            $item['created_at'] ?? 'N/A',
        ], $data);

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function getInvestmentPlansExportData(Request $request): array
    {
        $data = app(InvestmentPlanReportService::class)->export($request);
        $headers = ['Plan', 'Investors', 'Total Amount'];
        $rows = array_map(fn ($item) => [
            $item['market'] ?? 'N/A',
            $item['total_investments'] ?? 0,
            $item['total_amount'] ?? 0,
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
            $data = app(SubscriptionReportService::class)->list($params);
            $headers = ['User', 'Plan', 'Started', 'Expires', 'Auto Renew', 'Status'];
            $rows = array_map(fn ($item) => [
                $item['user'] ?? 'N/A',
                $item['plan'] ?? 'N/A',
                $item['started'] ?? 'N/A',
                $item['expires'] ?? 'N/A',
                $item['auto_renew'] ? 'Yes' : 'No',
                $item['status'] ?? 'N/A',
            ], $data);
        }

        return ['headers' => $headers, 'rows' => $rows, 'tab' => $tab];
    }

    protected function getWalletWithdrawalsExportData(Request $request): array
    {
        $tab = $request->get('tab', 'withdrawals');
        $params = array_merge($request->all(), ['per_page' => 10000]);
        $result = app(WalletReportService::class)->$tab($params);
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
                $item['user'] ?? 'N/A',
                $item['amount'] ?? 0,
                $item['type'] ?? 'N/A',
                $item['reason'] ?? 'N/A',
                $item['created_at'] ?? 'N/A',
            ], $data),
            default => [],
        };

        return ['headers' => $headers, 'rows' => $rows, 'tab' => $tab];
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
