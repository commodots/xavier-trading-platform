<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\UserReportService;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\InvestmentReportService;
use App\Services\Reports\WalletReportService;
use App\Services\Reports\WithdrawalReportService;
use App\Services\Reports\ReferralReportService;
use App\Services\Reports\SubscriptionReportService;
use App\Services\Reports\SystemReportService;
use App\Services\Reports\ReportExportService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ReportsController extends Controller
{
    protected DashboardReportService $dashboard;
    protected UserReportService $userReport;
    protected FinancialReportService $financial;
    protected InvestmentReportService $investment;
    protected WalletReportService $wallet;
    protected WithdrawalReportService $withdrawal;
    protected ReferralReportService $referral;
    protected SubscriptionReportService $subscription;
    protected SystemReportService $system;
    protected ReportExportService $export;

    public function __construct(
        DashboardReportService $dashboard,
        UserReportService $userReport,
        FinancialReportService $financial,
        InvestmentReportService $investment,
        WalletReportService $wallet,
        WithdrawalReportService $withdrawal,
        ReferralReportService $referral,
        SubscriptionReportService $subscription,
        SystemReportService $system,
        ReportExportService $export
    ) {
        $this->dashboard = $dashboard;
        $this->userReport = $userReport;
        $this->financial = $financial;
        $this->investment = $investment;
        $this->wallet = $wallet;
        $this->withdrawal = $withdrawal;
        $this->referral = $referral;
        $this->subscription = $subscription;
        $this->system = $system;
        $this->export = $export;
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
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    protected function exportUsers(Request $request)
    {
        $data = $this->userReport->export($request->all());
        $headers = ['Name', 'Email', 'Phone', 'Country', 'KYC Status', 'Status', 'Joined', 'Last Login'];
        $rows = array_map(fn($u) => [
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
        if (!file_exists($logFile)) {
            return response()->json(['logs' => []]);
        }
        $lines = tail($logFile, 100);
        $logs = array_map(fn($line) => ['message' => $line], $lines);
        return response()->json(['logs' => $logs]);
    }

    public function searchUsers(Request $request)
    {
        $query = $request->get('query', '');
        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(20)
            ->get(['id', 'name', 'email']);
        return response()->json(['users' => $users]);
    }

    public function investmentFilters()
    {
        return response()->json($this->investment->filters());
    }
}

if (!function_exists('tail')) {
    function tail($file, $lines = 100) {
        $fp = fopen($file, 'r');
        fseek($fp, -1, SEEK_END);
        $pos = ftell($fp);
        $output = [];
        $currentLine = '';

        while ($pos > 0 && count($output) < $lines) {
            $char = fgetc($fp);
            if ($char === "\n") {
                $output[] = strrev($currentLine);
                $currentLine = '';
            } else {
                $currentLine .= $char;
            }
            fseek($fp, --$pos, SEEK_SET);
        }

        if ($currentLine !== '') {
            $output[] = strrev($currentLine);
        }

        fclose($fp);
        return array_reverse($output);
    }
}
