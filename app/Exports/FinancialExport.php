<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $tab;

    public function __construct($data, string $tab = 'deposits')
    {
        $this->data = $data;
        $this->tab = $tab;
    }

    public function collection()
    {
        if ($this->data instanceof \Illuminate\Support\Collection) {
            return $this->data;
        }
        return collect($this->data);
    }

    public function headings(): array
    {
        return match ($this->tab) {
            'deposits' => ['Date', 'Reference', 'User', 'Amount', 'Currency', 'Method', 'Status'],
            'withdrawals' => ['Date', 'Reference', 'User', 'Amount', 'Currency', 'Method', 'Status'],
            'wallet_transactions' => ['Date', 'Reference', 'User', 'Type', 'Amount', 'Currency', 'Status'],
            'fees' => ['Date', 'Reference', 'User', 'Fee Type', 'Amount', 'Currency', 'Status'],
            'revenue' => ['Date', 'Source', 'Type', 'Amount', 'Currency', 'Status'],
            default => ['Date', 'Reference', 'User', 'Amount', 'Currency', 'Status'],
        };
    }

    public function map($row): array
    {
        $row = (array) $row;
        $date = $row['created_at'] ?? $row['date'] ?? 'N/A';
        if ($date instanceof \DateTime || $date instanceof \Carbon\Carbon) {
            $date = $date->format('Y-m-d H:i:s');
        }

        return match ($this->tab) {
            'deposits' => [
                $date,
                $row['reference'] ?? 'N/A',
                $row['user'] ?? $row['user_name'] ?? $row['user_id'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['method'] ?? $row['payment_method'] ?? 'N/A',
                $row['status'] ?? 'N/A',
            ],
            'withdrawals' => [
                $date,
                $row['reference'] ?? 'N/A',
                $row['user'] ?? $row['user_name'] ?? $row['user_id'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['method'] ?? $row['payment_method'] ?? 'N/A',
                $row['status'] ?? 'N/A',
            ],
            'wallet_transactions' => [
                $date,
                $row['reference'] ?? 'N/A',
                $row['user'] ?? $row['user_name'] ?? $row['user_id'] ?? 'N/A',
                $row['type'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['status'] ?? 'N/A',
            ],
            'fees' => [
                $date,
                $row['reference'] ?? 'N/A',
                $row['user'] ?? $row['user_name'] ?? $row['user_id'] ?? 'N/A',
                $row['fee_type'] ?? $row['type'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['status'] ?? 'N/A',
            ],
            'revenue' => [
                $date,
                $row['source'] ?? 'N/A',
                $row['type'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['status'] ?? 'N/A',
            ],
            default => [
                $date,
                $row['reference'] ?? 'N/A',
                $row['user'] ?? $row['user_name'] ?? 'N/A',
                $row['amount'] ?? 0,
                $row['currency'] ?? 'USD',
                $row['status'] ?? 'N/A',
            ],
        };
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0047AB']]],
        ];
    }
}