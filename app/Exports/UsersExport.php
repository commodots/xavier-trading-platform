<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        if ($this->data instanceof Collection) {
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
