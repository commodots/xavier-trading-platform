<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Reports\InvestmentReportService;
use Mockery;
use Tests\TestCase;

class InvestmentReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_summary_contains_key_metrics()
    {
        $orderMock = Mockery::mock('alias:'.Order::class);
        $orderMock->shouldReceive('count')->andReturn(10);
        $orderMock->shouldReceive('where')->andReturnSelf();
        $orderMock->shouldReceive('whereIn')->andReturnSelf();
        $orderMock->shouldReceive('sum')->andReturn(1000);

        $service = new InvestmentReportService;
        $summary = $service->summary();

        $this->assertArrayHasKey('total_investments', $summary);
        $this->assertArrayHasKey('active', $summary);
        $this->assertArrayHasKey('completed', $summary);
        $this->assertArrayHasKey('cancelled', $summary);
        $this->assertArrayHasKey('pending', $summary);
        $this->assertArrayHasKey('principal', $summary);
        $this->assertArrayHasKey('expected_roi', $summary);
        $this->assertArrayHasKey('paid_roi', $summary);
    }
}
