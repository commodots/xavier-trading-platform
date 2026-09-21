<?php

namespace Tests\Feature\CSL;

use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslStockBroker;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Trading\TradingExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CslOrderSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_records_provider_reference_and_keeps_local_reservation_until_confirmation(): void
    {
        /*
         * Http::fake below guarantees no request ever reaches CSL, so it is
         * safe to simulate an explicitly activated environment here.
         */
        config()->set('services.csl.mock', false);
        config()->set('services.csl.live_trading_enabled', true);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),

            '*/DoCRXTBuyOrder' => Http::response(
                $this->cslFixture('buy_accepted'),
                200
            ),
        ]);

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

        $order = app(TradingExecutionService::class, [
            'broker' => app(CslStockBroker::class),
        ])->submit($user, [
            'symbol' => 'TIP',
            'side' => 'buy',
            'quantity' => 10,
            'type' => 'limit',
            'limit_price' => 100,
            'market_price' => 100,
            'company' => 'Test Instrument',
        ]);

        /*
         * An accepted order stays open and unfilled until reconciliation
         * reports executions.
         */
        $this->assertSame('open', $order->status);
        $this->assertSame(0.0, (float) $order->filled_quantity);
        $this->assertSame('pending', $order->reconciliation_status);

        $this->assertSame('100001', $order->provider_order_id);

        $this->assertNotNull($order->provider_client_reference);
        $this->assertStringStartsWith('XAV-', $order->provider_client_reference);
        $this->assertSame(
            $order->provider_client_reference,
            $order->provider_request['xavier_client_reference']
        );

        $this->assertSame('NGX1', $order->provider_request['market_id']);
        $this->assertSame('ACC-1', $order->provider_request['market_account_id']);
        $this->assertSame('TIP', $order->provider_request['symbol_code']);

        // Funds stay reserved until CSL confirms execution.
        $this->assertSame(1000.0, (float) Wallet::first()->locked);
    }

    public function test_uncertain_submission_is_flagged_unknown_and_keeps_the_reservation(): void
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
        $broker->shouldReceive('buy')->once()->andThrow(
            new RuntimeException('Connection timed out')
        );

        try {
            app(TradingExecutionService::class, ['broker' => $broker])->submit($user, [
                'symbol' => 'TIP',
                'side' => 'buy',
                'quantity' => 10,
                'type' => 'limit',
                'limit_price' => 100,
                'market_price' => 100,
            ]);

            $this->fail('An uncertain provider failure must surface to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Connection timed out', $exception->getMessage());
        }

        /*
         * The order must survive the failed submit so reconciliation can work
         * out whether CSL actually accepted it, and the reservation must stay
         * locked because the outcome is unknown.
         */
        $order = Order::query()->where('provider', 'csl')->firstOrFail();

        $this->assertSame('open', $order->status);
        $this->assertSame('unknown', $order->reconciliation_status);
        $this->assertSame('Connection timed out', $order->provider_response['error']);
        $this->assertSame(1000.0, (float) Wallet::first()->locked);
    }
}
