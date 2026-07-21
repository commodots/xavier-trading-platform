<?php

namespace App\Services\Reports;

use App\Exports\TransactionsExport;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

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
        // Convert array to collection for export
        $collection = collect($data);
        return Excel::download(
            new TransactionsExport($collection),
            $filename . '.xlsx'
        );
    }
}