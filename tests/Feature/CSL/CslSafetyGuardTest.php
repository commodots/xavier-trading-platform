<?php

namespace Tests\Feature\CSL;

use App\Services\CSL\CslStockBroker;
use RuntimeException;
use Tests\TestCase;

class CslSafetyGuardTest extends TestCase
{
    public function test_live_trading_is_blocked_when_disabled(): void
    {
        config()->set('services.csl.mode', 'live');
        config()->set('services.csl.live_trading_enabled', false);
        config()->set('services.csl.mock', false);

        $broker = app(CslStockBroker::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSL live trading is disabled');

        $broker->buy([
            'market_id' => 'NGX1',
            'market_account_id' => 'TEST001',
            'symbol' => 'TIP',
            'quantity' => 10,
            'type' => 'limit',
            'limit_price' => 150,
        ]);
    }

    public function test_live_trading_is_blocked_in_test_mode_without_activation(): void
    {
        config()->set('services.csl.mode', 'test');
        config()->set('services.csl.live_trading_enabled', false);
        config()->set('services.csl.mock', false);

        $broker = app(CslStockBroker::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSL live trading is disabled');

        $broker->sell([
            'market_id' => 'NGX1',
            'market_account_id' => 'TEST001',
            'symbol' => 'TIP',
            'quantity' => 10,
            'type' => 'limit',
            'limit_price' => 150,
        ]);
    }

    public function test_mock_trading_is_allowed_without_live_activation(): void
    {
        config()->set('services.csl.live_trading_enabled', false);
        config()->set('services.csl.mock', true);

        $broker = app(CslStockBroker::class);

        $result = $broker->buy([
            'market_id' => 'NGX1',
            'market_account_id' => 'TEST-MA-001',
            'symbol' => 'TIP',
            'quantity' => 10,
            'type' => 'limit',
            'limit_price' => 150,
        ]);

        $this->assertSame('accepted', $result['status']);
    }
}
