<?php

namespace Tests\Unit;

use App\Services\Reports\InvestmentReportService;
use App\Services\Reports\ReportCacheService;
use Mockery;
use Tests\TestCase;

class InvestmentReportServiceTest extends TestCase
{
    public function test_summary_uses_cache()
    {
        $expected = [['label' => 'Total Investments', 'value' => 10, 'icon' => 'chart-bar', 'color' => '#0047AB']];

        $cacheMock = Mockery::mock(ReportCacheService::class);
        $cacheMock->shouldReceive('remember')->once()->andReturn($expected);

        $svc = new InvestmentReportService($cacheMock);
        $res = $svc->summary();

        $this->assertSame($expected, $res);
    }
}
