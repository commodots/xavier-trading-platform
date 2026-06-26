<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
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
            'format' => 'nullable|in:pdf,excel,csv',
        ]);

        $format = $request->format ?? 'json';
        $data = app(AccountStatementService::class)
            ->generate(
                $request->user(),
                $request->from,
                $request->to
            );

        if ($format === 'excel') {
            return Excel::download(
                new TransactionsExport($data),
                'account-statement.xlsx'
            );
        }

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Reference', 'Type', 'Amount', 'Status']);
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $row->created_at?->format('Y-m-d H:i:s'),
                            $row->reference ?? 'N/A',
                            $row->type ?? 'N/A',
                            $row->amount ?? 0,
                            $row->status ?? 'N/A',
                        ]);
                    }
                    fclose($file);
                },
                'account-statement.csv'
            );
        }

        if ($format === 'pdf') {
            $pdf = PDF::loadView('pdf.statement', [
                'rows' => $data,
                'from' => $request->from,
                'to' => $request->to,
            ]);
            return $pdf->download('account-statement.pdf');
        }

        return response()->json($data);
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
}