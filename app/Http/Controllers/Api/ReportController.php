<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\ReportHistory;
use App\Models\User;
use App\Notifications\ReportGeneratedNotification;
use App\Services\Audit\AuditService;
use App\Services\Reports\AccountStatementService;
use App\Services\Reports\AuditTrailService;
use App\Services\Reports\DepositReportService;
use App\Services\Reports\WithdrawalReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ReportController extends Controller
{
    public function accountStatement(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,pdf,excel,csv',
            'wallet' => 'nullable|string|in:all,USD,NGN,BTC,ETH',
        ]);

        $format = $request->format ?? 'json';
        $wallet = $request->wallet ?? 'all';
        $result = app(AccountStatementService::class)
            ->generate(
                $request->user(),
                $request->from,
                $request->to,
                $wallet
            );

        $ledger = $result['ledger'];
        $summary = $result['summary'];
        $period = $result['period'];
        $currentBalances = $result['current_balances'] ?? [];

        // Log audit trail for report generation
        AuditService::log(
            'report_generated',
            'account_statement',
            0,
            null,
            [
                'user_id' => $request->user()->id,
                'wallet' => $wallet,
                'from' => $request->from,
                'to' => $request->to,
                'format' => $format,
            ]
        );

        // Save download record for downloadable formats
        if (in_array($format, ['pdf', 'excel', 'csv'])) {
            try {
                $user = $request->user();
                $walletLabel = $wallet === 'all' ? 'All Wallets' : $wallet . ' Wallet';
                $typeLabel = $request->type === 'trading' ? 'Trading Performance' : 'Statement';
                
                ReportHistory::create([
                    'user_id' => $user->id,
                    'name' => "Account {$typeLabel} - {$walletLabel} ({$request->from} to {$request->to})",
                    'type' => $request->type ?? 'statement',
                    'format' => $format,
                    'wallet' => $wallet,
                    'period' => "{$request->from} to {$request->to}",
                    'start_date' => $request->from,
                    'end_date' => $request->to,
                    'status' => 'completed',
                ]);
            } catch (\Exception $e) {
                logger()->error('Failed to save report history: ' . $e->getMessage());
            }
        }

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($ledger, $period['from'], $period['to'], $summary),
                'account-statement.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($ledger) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status', 'Balance Before', 'Balance After']);
                    foreach ($ledger as $item) {
                        $tx = $item['transaction'];
                        fputcsv($file, [
                            $tx->created_at?->format('Y-m-d H:i:s'),
                            $tx->reference ?? 'N/A',
                            $tx->type ?? 'N/A',
                            number_format((float)($tx->amount ?? 0), 2),
                            $tx->status ?? 'N/A',
                            number_format((float)($item['balance_before'] ?? 0), 2),
                            number_format((float)($item['balance_after'] ?? 0), 2),
                        ]);
                    }
                    fclose($file);
                },
                'account-statement.csv'
            );
        }

        if ($format === 'pdf') {
            $pdf = PDF::loadView('pdf.statement', [
                'ledger' => $ledger,
                'summary' => $summary,
                'period' => $period,
                'current_balances' => $currentBalances,
            ]);
            return $pdf->download('account-statement.pdf');
        }

        return response()->json($result);
    }

    public function tradingPerformance(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,pdf,excel,csv',
            'wallet' => 'nullable|string|in:all,USD,NGN,BTC,ETH',
        ]);

        $format = $request->format ?? 'json';
        $wallet = $request->wallet ?? 'all';
        $result = app(AccountStatementService::class)
            ->generate(
                $request->user(),
                $request->from,
                $request->to,
                $wallet
            );

        // Filter ledger for trade transactions only
        $ledger = collect($result['ledger'])->filter(function ($item) {
            return $item['transaction']->type === 'trade';
        })->values()->toArray();

        $result['ledger'] = $ledger;
        $result['summary']['transaction_count'] = count($ledger);

        $period = $result['period'];

        // Log audit trail
        AuditService::log(
            'report_generated',
            'trading_performance',
            0,
            null,
            [
                'user_id' => $request->user()->id,
                'wallet' => $wallet,
                'from' => $request->from,
                'to' => $request->to,
                'format' => $format,
            ]
        );

        // Save download record for downloadable formats
        if (in_array($format, ['pdf', 'excel', 'csv'])) {
            try {
                $user = $request->user();
                $walletLabel = $wallet === 'all' ? 'All Wallets' : $wallet . ' Wallet';
                
                ReportHistory::create([
                    'user_id' => $user->id,
                    'name' => "Trading Performance - {$walletLabel} ({$request->from} to {$request->to})",
                    'type' => 'trading',
                    'format' => $format,
                    'wallet' => $wallet,
                    'period' => "{$request->from} to {$request->to}",
                    'start_date' => $request->from,
                    'end_date' => $request->to,
                    'status' => 'completed',
                ]);
            } catch (\Exception $e) {
                logger()->error('Failed to save report history: ' . $e->getMessage());
            }
        }

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($ledger, $period['from'], $period['to'], $result['summary']),
                'trading-performance.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($ledger) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status', 'Balance Before', 'Balance After']);
                    foreach ($ledger as $item) {
                        $tx = $item['transaction'];
                        fputcsv($file, [
                            $tx->created_at?->format('Y-m-d H:i:s'),
                            $tx->reference ?? 'N/A',
                            $tx->type ?? 'N/A',
                            number_format((float)($tx->amount ?? 0), 2),
                            $tx->status ?? 'N/A',
                            number_format((float)($item['balance_before'] ?? 0), 2),
                            number_format((float)($item['balance_after'] ?? 0), 2),
                        ]);
                    }
                    fclose($file);
                },
                'trading-performance.csv'
            );
        }

        if ($format === 'pdf') {
            $pdf = PDF::loadView('pdf.statement', [
                'ledger' => $ledger,
                'summary' => $result['summary'],
                'period' => $period,
                'current_balances' => $result['current_balances'] ?? [],
                'title' => 'Trading Performance Report',
            ]);
            return $pdf->download('trading-performance.pdf');
        }

        return response()->json($result);
    }

    public function depositRegister(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,pdf,excel,csv',
        ]);

        $format = $request->format ?? 'json';
        $data = app(DepositReportService::class)
            ->generate($request->from, $request->to);

        // Log audit trail
        AuditService::log(
            'report_generated',
            'deposit_register',
            0,
            null,
            [
                'admin_id' => $request->user()->id,
                'from' => $request->from,
                'to' => $request->to,
                'format' => $format,
            ]
        );

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($data),
                'deposit-register.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status', 'User']);
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $row->created_at?->format('Y-m-d H:i:s'),
                            $row->reference ?? 'N/A',
                            $row->type ?? 'N/A',
                            $row->amount ?? 0,
                            $row->status ?? 'N/A',
                            $row->user?->name ?? 'N/A',
                        ]);
                    }
                    fclose($file);
                },
                'deposit-register.csv'
            );
        }

        if ($format === 'pdf') {
            $rows = [];
            foreach ($data as $row) {
                $rows[] = [
                    'date' => $row->created_at?->format('Y-m-d H:i:s'),
                    'reference' => $row->reference ?? 'N/A',
                    'type' => $row->type ?? 'N/A',
                    'amount' => $row->amount ?? 0,
                    'status' => $row->status ?? 'N/A',
                    'user_name' => $row->user?->name ?? 'N/A',
                ];
            }
            $pdf = PDF::loadView('pdf.admin-report', [
                'title' => 'Deposit Register',
                'headers' => ['Date', 'Reference', 'Type', 'Amount', 'Status', 'User'],
                'rows' => $rows,
                'from' => $request->from,
                'to' => $request->to,
            ]);
            return $pdf->download('deposit-register.pdf');
        }

        return response()->json($data);
    }

    public function withdrawalRegister(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,pdf,excel,csv',
        ]);

        $format = $request->format ?? 'json';
        $data = app(WithdrawalReportService::class)
            ->generate($request->from, $request->to);

        // Log audit trail
        AuditService::log(
            'report_generated',
            'withdrawal_register',
            0,
            null,
            [
                'admin_id' => $request->user()->id,
                'from' => $request->from,
                'to' => $request->to,
                'format' => $format,
            ]
        );

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($data),
                'withdrawal-register.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status', 'User']);
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $row->created_at?->format('Y-m-d H:i:s'),
                            $row->reference ?? 'N/A',
                            $row->type ?? 'N/A',
                            $row->amount ?? 0,
                            $row->status ?? 'N/A',
                            $row->user?->name ?? 'N/A',
                        ]);
                    }
                    fclose($file);
                },
                'withdrawal-register.csv'
            );
        }

        if ($format === 'pdf') {
            $rows = [];
            foreach ($data as $row) {
                $rows[] = [
                    'date' => $row->created_at?->format('Y-m-d H:i:s'),
                    'reference' => $row->reference ?? 'N/A',
                    'type' => $row->type ?? 'N/A',
                    'amount' => $row->amount ?? 0,
                    'status' => $row->status ?? 'N/A',
                    'user_name' => $row->user?->name ?? 'N/A',
                ];
            }
            $pdf = PDF::loadView('pdf.admin-report', [
                'title' => 'Withdrawal Register',
                'headers' => ['Date', 'Reference', 'Type', 'Amount', 'Status', 'User'],
                'rows' => $rows,
                'from' => $request->from,
                'to' => $request->to,
            ]);
            return $pdf->download('withdrawal-register.pdf');
        }

        return response()->json($data);
    }

    public function auditTrail(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,pdf,excel,csv',
            'action' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $format = $request->format ?? 'json';
        $data = app(AuditTrailService::class)->generate($request->from, $request->to);

        // Log audit trail
        AuditService::log(
            'report_generated',
            'audit_trail_report',
            0,
            null,
            [
                'admin_id' => $request->user()->id,
                'from' => $request->from,
                'to' => $request->to,
                'action_filter' => $request->action,
                'user_filter' => $request->user_id,
            ]
        );

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($data),
                'audit-trail.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address']);
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $row->created_at?->format('Y-m-d H:i:s'),
                            $row->user?->name ?? 'System',
                            $row->action ?? 'N/A',
                            $row->entity_type ?? 'N/A',
                            $row->entity_id ?? 'N/A',
                            $row->ip_address ?? 'N/A',
                        ]);
                    }
                    fclose($file);
                },
                'audit-trail.csv'
            );
        }

        if ($format === 'pdf') {
            $rows = [];
            foreach ($data as $row) {
                $rows[] = [
                    'date' => $row->created_at?->format('Y-m-d H:i:s'),
                    'user_name' => $row->user?->name ?? 'System',
                    'action' => $row->action,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'ip_address' => $row->ip_address,
                ];
            }
            $pdf = PDF::loadView('pdf.admin-report', [
                'title' => 'Audit Trail Report',
                'headers' => ['Date', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address'],
                'rows' => $rows,
                'from' => $request->from,
                'to' => $request->to,
            ]);
            return $pdf->download('audit-trail.pdf');
        }

        return response()->json($data);
    }

    public function sendReportToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'required|in:pdf,excel,csv',
            'report_type' => 'required|in:account_statement,trading_performance',
            'wallet' => 'nullable|string|in:all,USD,NGN,BTC,ETH',
            'message' => 'nullable|string|max:500',
        ]);

        $admin = $request->user();
        $user = User::findOrFail($request->user_id);
        $wallet = $request->wallet ?? 'all';
        $format = $request->format;

        return $this->generateAndSendReport($admin, $user, $request, $wallet, $format);
    }

    public function sendReportToAllUsers(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'required|in:pdf,excel,csv',
            'report_type' => 'required|in:account_statement,trading_performance',
            'wallet' => 'nullable|string|in:all,USD,NGN,BTC,ETH',
            'message' => 'nullable|string|max:500',
        ]);

        $admin = $request->user();
        $wallet = $request->wallet ?? 'all';
        $format = $request->format;
        $users = User::all();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $result = $this->generateAndSendReport($admin, $user, $request, $wallet, $format, true);
                if ($result) $sent++;
                else $failed++;
            } catch (\Exception $e) {
                logger()->error("Failed to send report to user {$user->id}: " . $e->getMessage());
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Report sent to {$sent} users" . ($failed > 0 ? ". {$failed} failed." : "."),
        ]);
    }

    private function generateAndSendReport($admin, $user, $request, $wallet, $format, $bulk = false)
    {
        // Generate the report
        $result = app(AccountStatementService::class)
            ->generate(
                $user,
                $request->from,
                $request->to,
                $wallet
            );

        $ledger = $result['ledger'];

        // If trading performance, filter for trades only
        if ($request->report_type === 'trading_performance') {
            $ledger = collect($ledger)->filter(function ($item) {
                return $item['transaction']->type === 'trade';
            })->values()->toArray();
            $result['ledger'] = $ledger;
            $result['summary']['transaction_count'] = count($ledger);
        }

        $period = $result['period'];
        $summary = $result['summary'];
        $currentBalances = $result['current_balances'] ?? [];

        // Generate file content based on format
        $fileContent = null;
        $fileName = null;
        $mimeType = null;

        if ($format === 'excel') {
            $fileContent = Excel::raw(
                new TransactionsExport($ledger, $period['from'], $period['to'], $summary),
                \Maatwebsite\Excel\Excel::XLSX
            );
            $reportType = $request->report_type === 'trading_performance' ? 'trading-performance' : 'account-statement';
            $fileName = "{$reportType}-{$request->from}-to-{$request->to}.xlsx";
            $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'pdf') {
            $pdf = PDF::loadView('pdf.statement', [
                'ledger' => $ledger,
                'summary' => $summary,
                'period' => $period,
                'current_balances' => $currentBalances,
                'title' => $request->report_type === 'trading_performance' ? 'Trading Performance Report' : 'Account Statement',
            ]);
            $fileContent = $pdf->output();
            $reportType = $request->report_type === 'trading_performance' ? 'trading-performance' : 'account-statement';
            $fileName = "{$reportType}-{$request->from}-to-{$request->to}.pdf";
            $mimeType = 'application/pdf';
        } elseif ($format === 'csv') {
            $fileContent = $this->generateCsvContent($ledger);
            $reportType = $request->report_type === 'trading_performance' ? 'trading-performance' : 'account-statement';
            $fileName = "{$reportType}-{$request->from}-to-{$request->to}.csv";
            $mimeType = 'text/csv';
        }

        // Send notification to user with attachment
        try {
            $user->notify(new ReportGeneratedNotification(
                $user,
                $fileName,
                $fileContent,
                $mimeType,
                $request->from,
                $request->to,
                $request->report_type,
                $bulk ? null : ($request->message ?? null),
                $admin
            ));
        } catch (\Exception $e) {
            logger()->error("Failed to send report notification to user {$user->id}: " . $e->getMessage());
            return false;
        }

        // Save report history
        try {
            $walletLabel = $wallet === 'all' ? 'All Wallets' : $wallet . ' Wallet';
            $typeLabel = $request->report_type === 'trading_performance' ? 'Trading Performance' : 'Statement';
            
            ReportHistory::create([
                'user_id' => $user->id,
                'name' => "{$typeLabel} - {$walletLabel} ({$request->from} to {$request->to}) [Sent by admin" . ($bulk ? ' - Bulk' : '') . "]",
                'type' => $request->report_type,
                'format' => $format,
                'wallet' => $wallet,
                'period' => "{$request->from} to {$request->to}",
                'start_date' => $request->from,
                'end_date' => $request->to,
                'status' => 'completed',
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to save report history: ' . $e->getMessage());
        }

        // Log audit trail
        AuditService::log(
            'report_sent_to_user',
            'report',
            0,
            null,
            [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'report_type' => $request->report_type,
                'format' => $format,
                'from' => $request->from,
                'to' => $request->to,
                'wallet' => $wallet,
                'bulk' => $bulk,
            ]
        );

        return true;
    }

    public function reportHistory(Request $request)
    {
        $reports = ReportHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'name' => $report->name,
                    'format' => $report->format,
                    'period' => $report->period,
                    'created_at' => $report->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'reports' => $reports
        ]);
    }

    public function searchUsers(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $users = User::where('name', 'like', "%{$request->query}%")
            ->orWhere('email', 'like', "%{$request->query}%")
            ->limit(20)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

        return response()->json([
            'users' => $users,
        ]);
    }

    private function generateCsvContent($ledger)
    {
        $file = fopen('php://temp', 'w');
        fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status', 'Balance Before', 'Balance After']);
        foreach ($ledger as $item) {
            $tx = $item['transaction'];
            fputcsv($file, [
                $tx->created_at?->format('Y-m-d H:i:s'),
                $tx->reference ?? 'N/A',
                $tx->type ?? 'N/A',
                number_format((float)($tx->amount ?? 0), 2),
                $tx->status ?? 'N/A',
                number_format((float)($item['balance_before'] ?? 0), 2),
                number_format((float)($item['balance_after'] ?? 0), 2),
            ]);
        }
        rewind($file);
        $content = stream_get_contents($file);
        fclose($file);
        return $content;
    }
}