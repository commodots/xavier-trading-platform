<?php

namespace Tests\Unit;

use App\Services\Reports\FinancialReportService;
use Mockery;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    public function test_get_statistics_for_deposits_and_revenue()
    {
        // Mock Transaction static calls
        Mockery::mock('alias:App\\Models\\Transaction')
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('whereDate')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(1000);

        // Mock RevenueRecord for revenue type
        Mockery::mock('alias:App\\Models\\RevenueRecord')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('whereDate')->andReturnSelf()
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(5000);

        $svc = new FinancialReportService;

        $deposits = $svc->getStatistics('deposits');
        $this->assertIsArray($deposits);
        $this->assertEquals(1000, $deposits['today']);

        $revenue = $svc->getStatistics('revenue');
        $this->assertIsArray($revenue);
        $this->assertEquals(5000, $revenue['today']);
    }
}
