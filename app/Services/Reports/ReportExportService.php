<?php

namespace App\Services\Reports;

use App\Exports\TransactionsExport;
use App\Exports\UsersExport;
use App\Exports\FinancialExport;
use App\Exports\InvestmentsExport;
use App\Exports\WalletExport;
use App\Exports\ReferralExport;
use App\Exports\SubscriptionExport;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ReportExportService
{
    public function csv(array $data, array $headers, string $filename): \Illuminate\Http\Response
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
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }

    public function excel(array $data, array $headers, string $filename)
    {
        $collection = collect($data);
        return Excel::download(
            new TransactionsExport($collection),
            $filename . '.xlsx'
        );
    }

    public function excelWithExport($export, string $filename)
    {
        return Excel::download($export, $filename . '.xlsx');
    }

    public function pdf(string $view, array $data, string $filename)
    {
        $pdf = PDF::loadView($view, $data);
        return $pdf->download($filename . '.pdf');
    }

    /**
     * Route export based on format string.
     */
    public function export(string $format, array $rows, array $headers, string $title, string $from, string $to, ?string $view = null)
    {
        $safeTitle = strtolower(str_replace(' ', '-', $title));

        return match ($format) {
            'csv' => $this->csv($rows, $headers, $safeTitle),
            'excel' => $this->excel($rows, $headers, $safeTitle),
            'pdf' => $this->pdf(
                $view ?? 'pdf.admin-report',
                [
                    'title' => $title,
                    'headers' => $headers,
                    'rows' => $rows,
                    'from' => $from,
                    'to' => $to,
                ],
                $safeTitle
            ),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }
}