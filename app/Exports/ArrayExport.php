<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ArrayExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    protected array $headers;

    protected array $rows;

    protected string $reportType;

    public function __construct(array $headers, array $rows, string $reportType = '')
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->reportType = $reportType;
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function registerEvents(): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headers));
        $subtitle = $this->reportType ? ucwords(str_replace(['-', '_'], ' ', $this->reportType)) : 'Report';

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastColumn, $subtitle) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'XAVIER TRADING PLATFORM');
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0047AB');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A2', $subtitle);
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('666666');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $headerRange = "A4:{$lastColumn}4";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0047AB');
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
