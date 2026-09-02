<?php

namespace Tests\Unit;

use App\Services\Reports\FinancialReportService;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class FinancialReportServiceTest extends TestCase
{
    public function test_get_statistics_for_deposits_and_revenue()
    {
        Mockery::mock('alias:App\\Models\\NewTransaction')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('whereDate')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(1000);

        Mockery::mock('alias:App\\Models\\PlatformEarning')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('whereDate')->andReturnSelf()
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(5000);

        Mockery::mock('alias:App\\Models\\WithdrawalRequest')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(0);

        Mockery::mock('alias:App\\Models\\Fee')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(0);

        Mockery::mock('alias:App\\Models\\Wallet')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('sum')->andReturn(0);

        $svc = new FinancialReportService;

        $deposits = $svc->getStatistics('deposits');
        $this->assertIsArray($deposits);
        $this->assertEquals(1000, $deposits['today']);

        $revenue = $svc->getStatistics('revenue');
        $this->assertIsArray($revenue);
        $this->assertEquals(5000, $revenue['today']);
    }

    public function test_summary_uses_new_transaction_and_withdrawal_request_models()
    {
        Mockery::mock('alias:App\\Models\\NewTransaction')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->with('type', 'deposit')->andReturnSelf()
            ->shouldReceive('where')->with('currency', Mockery::any())->andReturnSelf()
            ->shouldReceive('where')->with('type', 'commission')->andReturnSelf()
            ->shouldReceive('sum')->with('amount')->andReturn(2500);

        Mockery::mock('alias:App\\Models\\WithdrawalRequest')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->with('status', 'pending')->andReturnSelf()
            ->shouldReceive('where')->with('currency', Mockery::any())->andReturnSelf()
            ->shouldReceive('sum')->with('amount')->andReturn(800);

        Mockery::mock('alias:App\\Models\\Wallet')
            ->shouldReceive('where')->with('currency', 'NGN')->andReturnSelf()
            ->shouldReceive('sum')->with('ngn_cleared')->andReturn(12000)
            ->shouldReceive('where')->with('currency', 'USD')->andReturnSelf()
            ->shouldReceive('sum')->with('usd_cleared')->andReturn(0);

        Mockery::mock('alias:App\\Models\\Fee')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('where')->with('type', 'fee')->andReturnSelf()
            ->shouldReceive('sum')->with('amount')->andReturn(150);

        Mockery::mock('alias:App\\Models\\PlatformEarning')
            ->shouldReceive('query')->andReturnSelf()
            ->shouldReceive('sum')->with('amount')->andReturn(6000);

        $svc = new FinancialReportService;
        $summary = $svc->summary();

        $this->assertEquals(2500, $summary[0]['value']);
        $this->assertEquals(800, $summary[4]['value']);
        $this->assertEquals(12000, $summary[6]['value']);
    }
}
