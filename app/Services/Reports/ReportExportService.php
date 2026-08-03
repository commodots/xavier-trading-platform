<?php

namespace App\Services\Reports;

use App\Exports\ArrayExport;
use App\Exports\ExpenseReportExport;
use App\Exports\ProfitLossExport;
use App\Exports\RevenueReportExport;
use App\Exports\TransactionsExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportService
{
    public function csv(array $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\Response
    {
        $callback = function () use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    public function excel(array $data, array $headers, string $filename, string $reportLabel = '')
    {
        // If headers are provided, use a generic array export so arbitrary
        // tabular data can be exported as Excel.
        if (! empty($headers)) {
            return Excel::download(new ArrayExport($headers, $data, $reportLabel ?? ''), $filename.'.xlsx');
        }

        $collection = collect($data);

        return Excel::download(
            new TransactionsExport($collection),
            $filename.'.xlsx'
        );
    }

    public function excelWithExport($export, string $filename)
    {
        return Excel::download($export, $filename.'.xlsx');
    }

    public function pdf(string $view, array $data, string $filename)
    {
        $pdf = PDF::loadView($view, $data);

        return $pdf->download($filename.'.pdf');
    }

    /**
     * Route export based on format string.
     */
    public function export(string $format, array $data, string $reportType, string $from, string $to)
    {
        $safeTitle = strtolower(str_replace(' ', '-', ($data['tab'] ?? $reportType)));

        // If the data contains explicit headers/rows, route through the
        // simple CSV/Excel/PDF exporters for generic tabular exports.
        if (isset($data['headers']) && isset($data['rows'])) {
            return match ($format) {
                'csv' => $this->csv($data['rows'], $data['headers'], $safeTitle),
                'excel' => $this->excel($data['rows'], $data['headers'], $safeTitle, ($data['tab'] ?? $reportType)),
                'pdf' => $this->pdf('pdf.admin-report', [
                    'title' => ucwords(str_replace('-', ' ', ($data['tab'] ?? $reportType))),
                    'headers' => $data['headers'],
                    'rows' => $data['rows'],
                    'from' => $from,
                    'to' => $to,
                ], $safeTitle),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };
        }

        return match ($format) {
            'csv' => $this->exportCsv($data, $reportType),
            'excel' => $this->exportExcel($data, $reportType),
            'pdf' => $this->exportPdf($data, $reportType, $from, $to),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    protected function exportCsv(array $data, string $reportType): \Symfony\Component\HttpFoundation\Response
    {
        $headers = [];
        $rows = [];

        match ($reportType) {
            'revenue' => $this->formatRevenueCsv($data, $headers, $rows),
            'profit-loss' => $this->formatProfitLossCsv($data, $headers, $rows),
            'expenses' => $this->formatExpensesCsv($data, $headers, $rows),
            default => [],
        };

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$reportType.'.csv"',
        ]);
    }

    protected function exportExcel(array $data, string $reportType)
    {
        return match ($reportType) {
            'revenue' => Excel::download(new RevenueReportExport($data), 'revenue-report.xlsx'),
            'profit-loss' => Excel::download(new ProfitLossExport($data), 'profit-loss-report.xlsx'),
            'expenses' => Excel::download(new ExpenseReportExport($data), 'expense-report.xlsx'),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };
    }

    protected function exportPdf(array $data, string $reportType, string $from, string $to)
    {
        $view = match ($reportType) {
            'revenue' => 'pdf.revenue-report',
            'profit-loss' => 'pdf.profit-loss-report',
            'expenses' => 'pdf.expense-report',
            default => 'pdf.admin-report',
        };

        return PDF::loadView($view, array_merge($data, ['from' => $from, 'to' => $to]))
            ->download($reportType.'.pdf');
    }

    protected function formatRevenueCsv(array $data, array &$headers, array &$rows): void
    {
        $headers = ['Metric', 'Value'];
        $rows[] = ['Period', $data['from'].' to '.$data['to']];
        $rows[] = ['Today', '$'.number_format($data['summary']['today'] ?? 0, 2)];
        $rows[] = ['This Month', '$'.number_format($data['summary']['month'] ?? 0, 2)];
        $rows[] = ['This Year', '$'.number_format($data['summary']['year'] ?? 0, 2)];
        $rows[] = ['Total Revenue', '$'.number_format($data['summary']['total'] ?? 0, 2)];
        $rows[] = [];
        $rows[] = ['Revenue Source', 'Amount', 'Percentage'];
        foreach ($data['table'] as $row) {
            $rows[] = [$row['source'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
    }

    protected function formatProfitLossCsv(array $data, array &$headers, array &$rows): void
    {
        $headers = ['Metric', 'Value'];
        $rows[] = ['Period', $data['from'].' to '.$data['to']];
        $rows[] = ['Total Income', '$'.number_format($data['profit']['income'] ?? 0, 2)];
        $rows[] = ['Total Expenses', '$'.number_format($data['profit']['expenses'] ?? 0, 2)];
        $rows[] = ['Net Profit', '$'.number_format($data['profit']['net_profit'] ?? 0, 2)];
        $rows[] = ['Profit Margin', ($data['profit']['margin'] ?? 0).'%'];
        $rows[] = [];
        $rows[] = ['Source', 'Amount', 'Percentage'];
        foreach ($data['income'] as $row) {
            $rows[] = [$row['source'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
        $rows[] = [];
        $rows[] = ['Category', 'Amount', 'Percentage'];
        foreach ($data['expenses'] as $row) {
            $rows[] = [$row['category'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
    }

    protected function formatExpensesCsv(array $data, array &$headers, array &$rows): void
    {
        $headers = ['Metric', 'Value'];
        $rows[] = ['Period', $data['from'].' to '.$data['to']];
        $rows[] = ['Total Expenses', '$'.number_format($data['summary']['total'] ?? 0, 2)];
        $rows[] = ['Outstanding', '$'.number_format($data['summary']['outstanding'] ?? 0, 2)];
        $rows[] = ['Average Monthly', '$'.number_format($data['summary']['average_monthly'] ?? 0, 2)];
        $rows[] = ['Largest Category', $data['summary']['largest_category']['name'] ?? 'N/A'];
        $rows[] = [];
        $rows[] = ['Category', 'Amount', 'Percentage'];
        foreach ($data['chart'] as $row) {
            $rows[] = [$row['category'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
        $rows[] = [];
        $rows[] = ['Date', 'Category', 'Vendor', 'Amount', 'Status'];
        foreach ($data['transactions'] as $row) {
            $rows[] = [$row['date'], $row['category'], $row['vendor'], '$'.number_format($row['amount'], 2), ucfirst($row['status'])];
        }
    }
}
