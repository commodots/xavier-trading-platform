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

        // Summary Sheet
        $rows[] = ['Revenue Report Summary'];
        $rows[] = [];
        $rows[] = ['Period', $this->data['from'].' to '.$this->data['to']];
        $rows[] = ['Generated', date('Y-m-d H:i:s')];
        $rows[] = [];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Today', '$'.number_format($this->data['summary']['today'] ?? 0, 2)];
        $rows[] = ['This Month', '$'.number_format($this->data['summary']['month'] ?? 0, 2)];
        $rows[] = ['This Year', '$'.number_format($this->data['summary']['year'] ?? 0, 2)];
        $rows[] = ['Total Revenue', '$'.number_format($this->data['summary']['total'] ?? 0, 2)];
        $rows[] = [];
        $rows[] = ['Revenue by Source'];
        $rows[] = ['Source', 'Amount', 'Percentage'];
        foreach ($this->data['table'] as $row) {
            $rows[] = [$row['source'], '$'.number_format($row['amount'], 2), $row['percentage'].'%'];
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
            'A6:C6' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
            'A12:C12' => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0047AB']], 'color' => ['argb' => 'FFFFFFFF']],
        ];
    }
}
