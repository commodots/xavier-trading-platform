<?php

namespace Tests\Unit;

use App\Services\Reports\ReportExportService;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    public function test_csv_export_returns_response()
    {
        $svc = app(ReportExportService::class);
        $headers = ['A', 'B'];
        $rows = [
            ['one', 'two'],
            ['three', 'four'],
        ];

        $res = $svc->csv($rows, $headers, 'test-csv');
        $this->assertNotNull($res);
        $this->assertTrue(method_exists($res, 'send') || method_exists($res, 'getContent'));
    }

    public function test_excel_export_returns_binary_response()
    {
        $svc = app(ReportExportService::class);
        $headers = ['A', 'B'];
        $rows = [
            ['one', 'two'],
            ['three', 'four'],
        ];

        $res = $svc->excel($rows, $headers, 'test-excel');
        $this->assertNotNull($res);
    }
}
