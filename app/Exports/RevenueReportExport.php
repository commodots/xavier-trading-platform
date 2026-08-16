<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueReportExport implements FromArray, WithStyles, WithTitle
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['Xavier Revenue Report'];
        $rows[] = [];
        $rows[] = ['Generated', date('Y-m-d H:i:s')];
        $rows[] = [];

        // Summary
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total Revenue', '₦'.number_format($this->data['summary']['total_revenue'] ?? 0, 2)];
        $rows[] = ['Revenue This Month', '₦'.number_format($this->data['summary']['revenue_this_month'] ?? 0, 2)];
        $rows[] = ['Revenue This Year', '₦'.number_format($this->data['summary']['revenue_this_year'] ?? 0, 2)];
        $rows[] = ['Transaction Count', $this->data['summary']['transaction_count'] ?? 0];
        $rows[] = ['Average Revenue', '₦'.number_format($this->data['summary']['average_revenue'] ?? 0, 2)];
        $rows[] = [];

        // Revenue by Source
        $rows[] = ['Revenue by Source'];
        $rows[] = ['Source', 'Amount (₦)'];
        if (isset($this->data['charts'][1])) {
            $chart = $this->data['charts'][1];
            foreach ($chart['labels'] as $index => $label) {
                $rows[] = [$label, '₦'.number_format($chart['series'][0]['data'][$index] ?? 0, 2)];
            }
        }
        $rows[] = [];

        // Monthly Revenue
        $rows[] = ['Monthly Revenue'];
        $rows[] = ['Period', 'Amount (₦)'];
        if (isset($this->data['charts'][0])) {
            $chart = $this->data['charts'][0];
            foreach ($chart['labels'] as $index => $label) {
                $rows[] = [$label, '₦'.number_format($chart['series'][0]['data'][$index] ?? 0, 2)];
            }
        }
        $rows[] = [];

        // Detailed register
        $rows[] = ['Detailed Revenue Register'];
        $rows[] = ['Date', 'Source', 'Reference', 'Description', 'User', 'Amount (₦)', 'Status'];
        $table = is_array($this->data['table'] ?? null) && isset($this->data['table']['data'])
            ? $this->data['table']['data']
            : ($this->data['table'] ?? []);
        foreach ($table as $row) {
            $rows[] = [
                $row['date'] ?? 'N/A',
                $row['source_label'] ?? $row['source'] ?? 'N/A',
                $row['reference'] ?? 'N/A',
                $row['description'] ?? 'N/A',
                $row['user_id'] ?? 'N/A',
                '₦'.number_format($row['amount'] ?? 0, 2),
                ucfirst($row['status'] ?? 'N/A'),
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Revenue Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            'A1' => ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF0047AB']]],
            'A5:G5' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
            'A12:C12' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
            'A18:C18' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
        ];
    }
}