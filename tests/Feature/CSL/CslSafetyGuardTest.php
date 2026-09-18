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
}
