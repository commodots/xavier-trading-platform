<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseReportExport implements FromArray, WithStyles, WithTitle
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        // Summary
        $rows[] = ['Expense Report'];
        $rows[] = [];
        $rows[] = ['Period', $this->data['from'].' to '.$this->data['to']];
        $rows[] = ['Generated', date('Y-m-d H:i:s')];
        $rows[] = [];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total Expenses', '$'.number_format($this->data['summary']['total'] ?? 0, 2)];
        $rows[] = ['Outstanding', '$'.number_format($this->data['summary']['outstanding'] ?? 0, 2)];
        $rows[] = ['Average Monthly', '$'.number_format($this->data['summary']['average_monthly'] ?? 0, 2)];
        $rows[] = ['Largest Category', $this->data['summary']['largest_category']['name'] ?? 'N/A'];
        $rows[] = [];
        // Category Breakdown
        $rows[] = ['Expenses by Category'];
        $rows[] = ['Category', 'Amount', 'Percentage'];
        foreach ($this->data['chart'] as $row) {
            $rows[] = [$row['category'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
        $rows[] = [];
        // Transactions
        $rows[] = ['Expense Details'];
        $rows[] = ['Date', 'Category', 'Vendor', 'Amount', 'Status'];
        foreach ($this->data['transactions'] as $row) {
            $rows[] = [$row['date'], $row['category'], $row['vendor'], '$'.number_format($row['amount'], 2), ucfirst($row['status'])];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Expense Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            'A1' => ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF0047AB']]],
            'A6:D6' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
            'A14:D14' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
        ];
    }
}
