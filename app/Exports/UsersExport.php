<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
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
        return [
            'Name',
            'Email',
            'Phone',
            'Country',
            'KYC Status',
            'Status',
            'Joined',
            'Last Login',
        ];
    }

    public function map($row): array
    {
        return [
            $row['name'] ?? 'N/A',
            $row['email'] ?? 'N/A',
            $row['phone'] ?? 'N/A',
            $row['country'] ?? 'N/A',
            $row['kyc_status'] ?? 'N/A',
            $row['status'] ?? 'N/A',
            $row['joined'] ?? 'N/A',
            $row['last_login'] ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0047AB']]],
        ];
    }
}