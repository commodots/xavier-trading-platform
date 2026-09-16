<?php

namespace Tests\Feature\CSL;

use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Trading\TradingExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslOrderSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_records_provider_reference_and_keeps_local_reservation_until_confirmation(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
            'status' => 'active',
        ]);

        ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'csl',
            'market_id' => 'NGX1',
            'market_account_id' => 'ACC-1',
            'status' => 'active',
        ]);

        $broker = Mockery::mock(StockBroker::class);
        $broker->shouldReceive('buy')->once()->withArgs(function (array $payload) {
            $this->assertSame('NGX1', $payload['market_id']);
            $this->assertSame('ACC-1', $payload['market_account_id']);
            $this->assertArrayHasKey('xavier_client_reference', $payload);
            $this->assertStringStartsWith('XAV-', $payload['xavier_client_reference']);

            return true;
        })->andReturn($this->cslFixture('buy_accepted'));

        $order = app(TradingExecutionService::class, ['broker' => $broker])->submit($user, [
            'symbol' => 'TEST',
            'side' => 'buy',
            'quantity' => 10,
            'type' => 'limit',
            'limit_price' => 100,
            'market_price' => 100,
            'company' => 'Test Company',
        ]);

        $this->assertSame('open', $order->status);
        $this->assertSame('CSL-ORDER-1', $order->provider_order_id);
        $this->assertNotNull($order->provider_client_reference);
        $this->assertSame($order->provider_client_reference, $order->provider_request['xavier_client_reference']);
        $this->assertSame(1000.0, (float) Wallet::first()->locked);
    }
}
