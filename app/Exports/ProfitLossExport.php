<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProfitLossExport implements FromArray, WithStyles, WithTitle
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
        $rows[] = ['Profit & Loss Report'];
        $rows[] = [];
        $rows[] = ['Period', $this->data['from'].' to '.$this->data['to']];
        $rows[] = ['Generated', date('Y-m-d H:i:s')];
        $rows[] = [];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total Income', '$'.number_format($this->data['profit']['income'] ?? 0, 2)];
        $rows[] = ['Total Expenses', '$'.number_format($this->data['profit']['expenses'] ?? 0, 2)];
        $rows[] = ['Net Profit', '$'.number_format($this->data['profit']['net_profit'] ?? 0, 2)];
        $rows[] = ['Profit Margin', ($this->data['profit']['margin'] ?? 0).'%'];
        $rows[] = [];
        // Income Breakdown
        $rows[] = ['Income Breakdown'];
        $rows[] = ['Source', 'Amount', 'Percentage'];
        foreach ($this->data['income'] as $row) {
            $rows[] = [$row['source'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }
        $rows[] = [];
        // Expenses Breakdown
        $rows[] = ['Expenses Breakdown'];
        $rows[] = ['Category', 'Amount', 'Percentage'];
        foreach ($this->data['expenses'] as $row) {
            $rows[] = [$row['category'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Profit & Loss';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            'A1' => ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF0047AB']]],
            'A6:D6' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
            'A13:D13' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
        ];
    }
}
