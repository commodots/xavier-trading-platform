<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $data;
    protected $startDate;
    protected $endDate;

    public function __construct($data, $startDate = null, $endDate = null)
    {
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        if ($this->data instanceof \Illuminate\Support\Collection) {
            return $this->data;
        }
        if (is_array($this->data)) {
            return collect($this->data);
        }
        if (is_object($this->data) && method_exists($this->data, 'items')) {
            return collect($this->data->items());
        }
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference',
            'Type',
            'Amount',
            'Balance Before',
            'Balance After',
            'Status',
        ];
    }

    public function map($row): array
    {
        if (isset($row['transaction'])) {
            //ledger format with balances
            $tx = $row['transaction'];
            return [
                $tx->created_at?->format('Y-m-d H:i:s'),
                $tx->reference ?? 'N/A',
                $tx->type ?? 'N/A',
                $tx->amount ?? 0,
                number_format($row['balance_before'], 2),
                number_format($row['balance_after'], 2),
                $tx->status ?? 'N/A',
            ];
        }
        
        // Legacy format
        return [
            $row->created_at?->format('Y-m-d H:i:s'),
            $row->reference ?? 'N/A',
            $row->type ?? 'N/A',
            $row->amount ?? 0,
            'N/A',
            'N/A',
            $row->status ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row (row 4, after title and info rows)
            4 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0047AB']]],
        ];
    }

    public function title(): string
    {
        return 'Account Statement';
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set title in row 1
                $sheet->setCellValue('A1', 'XAVIER TRADING PLATFORM');
                $sheet->mergeCells('A1:G1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0047AB');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::CENTER);
                
                // Set subtitle in row 2
                $sheet->setCellValue('A2', 'Account Statement');
                $sheet->mergeCells('A2:G2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('666666');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::CENTER);
                
                // Add blank row 3
                $sheet->setCellValue('A3', '');
                
                // Add info section starting row 4 (header will be row 4, data starts row 5)
                if ($this->startDate && $this->endDate) {
                    $sheet->setCellValue('A4', 'Period:');
                    $sheet->setCellValue('B4', $this->startDate . ' to ' . $this->endDate);
                    $sheet->mergeCells('B4:G4');
                    $sheet->getStyle('A4')->getFont()->setBold(true);
                    
                    $sheet->setCellValue('A5', 'Generated On:');
                    $sheet->setCellValue('B5', date('Y-m-d H:i:s'));
                    $sheet->mergeCells('B5:G5');
                    $sheet->getStyle('A5')->getFont()->setBold(true);
                    
                    $sheet->setCellValue('A6', 'Total Transactions:');
                    $sheet->setCellValue('B6', is_array($this->data) ? count($this->data) : $this->data->count());
                    $sheet->mergeCells('B6:G6');
                    $sheet->getStyle('A6')->getFont()->setBold(true);
                    
                    // Add blank row 7
                    $sheet->setCellValue('A7', '');
                    
                    // Headers will be in row 8, data starts row 9
                    $headerRow = 8;
                } else {
                    // Headers in row 4, data starts row 5
                    $headerRow = 4;
                }
                
                // Style the header row
                $sheet->getStyle($headerRow)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($headerRow)->getFill()->getStartColor()->setRGB('0047AB');
                $sheet->getStyle($headerRow)->getAlignment()->setHorizontal(Alignment::CENTER);
                
                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(20);
            },
        ];
    }
}
