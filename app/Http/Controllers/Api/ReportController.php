<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\ReportHistory;
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
                            $tx->amount ?? 0,
                            $tx->status ?? 'N/A',
                            number_format($item['balance_before'], 2),
                            number_format($item['balance_after'], 2),
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

    public function depositRegister(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:pdf,excel,csv',
        ]);

        $format = $request->format ?? 'json';
        $data = app(DepositReportService::class)
            ->generate($request->from, $request->to);

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

        return response()->json($data);
    }

    public function withdrawalRegister(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:pdf,excel,csv',
        ]);

        $format = $request->format ?? 'json';
        $data = app(WithdrawalReportService::class)
            ->generate($request->from, $request->to);

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

        return response()->json($data);
    }

    public function auditTrail(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $data = app(AuditTrailService::class)
            ->generate($request->from, $request->to);

        return response()->json($data);
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
}