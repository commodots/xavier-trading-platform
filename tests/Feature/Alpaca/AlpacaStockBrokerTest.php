<?php

namespace Tests\Feature\Alpaca;

use App\Providers\AlpacaProvider;
use App\Services\Stocks\AlpacaStockBroker;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AlpacaStockBrokerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock transport: no order ever leaves Xavier in this suite.
        config()->set('services.alpaca.mock', true);
    }

    public function test_market_buy_is_translated_into_an_alpaca_order_payload(): void
    {
        $result = app(AlpacaStockBroker::class)->buy([
            'symbol' => 'aapl',
            'quantity' => 2.5,
            'type' => 'market',
            'time_in_force' => 'day',
            'client_reference' => 'XAV-TEST-1',
        ]);

        $this->assertSame('alpaca', $result['provider']);
        $this->assertSame('buy', $result['side']);
        $this->assertSame('accepted', $result['status']);

        $this->assertSame('AAPL', $result['request']['symbol']);
        $this->assertSame(2.5, $result['request']['qty']);
        $this->assertSame('XAV-TEST-1', $result['request']['client_order_id']);

        $this->assertNotNull($result['provider_order_id']);

        // An accepted order is never reported filled here.
        $this->assertSame('0', $result['response']['filled_qty']);
    }

    public function test_fractional_quantity_survives_the_payload_untouched(): void
    {
        $result = app(AlpacaStockBroker::class)->sell([
            'symbol' => 'AAPL',
            'quantity' => 0.25,
            'type' => 'market',
            'time_in_force' => 'day',
        ]);

        $this->assertSame('sell', $result['side']);
        $this->assertSame(0.25, $result['request']['qty']);
    }

    public function test_bracket_order_carries_both_protective_legs(): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('buy')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['type'] === 'market'
                    && $payload['order_class'] === 'bracket'
                    && $payload['take_profit'] === ['limit_price' => 220.0]
                    && $payload['stop_loss'] === ['stop_price' => 180.0];
            }))
            ->andReturn($this->alpacaFixture('order_accepted'));

        $result = (new AlpacaStockBroker($provider))->buy([
            'symbol' => 'AAPL',
            'quantity' => 1,
            'type' => 'bracket',
            'take_profit' => 220,
            'stop_loss' => 180,
        ]);

        $this->assertSame('accepted', $result['status']);
        $this->assertSame('bracket', $result['request']['order_class']);
        $this->assertSame(['limit_price' => 220.0], $result['request']['take_profit']);
    }

    public function test_bracket_order_requires_both_protective_legs(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bracket orders require both take profit and stop loss.');

        app(AlpacaStockBroker::class)->buy([
            'symbol' => 'AAPL',
            'quantity' => 1,
            'type' => 'bracket',
            'take_profit' => 220,
        ]);
    }

    public function test_a_provider_rejection_is_reported_as_rejected_never_as_filled(): void
    {
        $provider = Mockery::mock(AlpacaProvider::class);
        $provider->shouldReceive('buy')->once()->andReturn([
            'id' => 'b1f2c3d4-0001-4a1b-8c2d-000000000001',
            'status' => 'rejected',
            'reject_reason' => 'insufficient buying power',
        ]);

        $result = (new AlpacaStockBroker($provider))->buy([
            'symbol' => 'AAPL',
            'quantity' => 1,
            'type' => 'market',
        ]);

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('insufficient buying power', $result['remarks']);
    }

    public function test_live_trading_guard_refuses_orders_when_no_transport_is_enabled(): void
    {
        config()->set('services.alpaca.mock', false);
        config()->set('services.alpaca.live_trading_enabled', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alpaca live trading is disabled.');

        app(AlpacaStockBroker::class)->buy([
            'symbol' => 'AAPL',
            'quantity' => 1,
            'type' => 'market',
        ]);
    }
}
