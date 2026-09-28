<?php

namespace Tests\Feature\Alpaca;

use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslStockBroker;
use App\Services\Stocks\AlpacaStockBroker;
use App\Services\Stocks\Exceptions\ProviderRequestException;
use App\Services\Trading\TradingExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AlpacaOrderSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.alpaca.mock', true);
    }

    public function test_market_routing_selects_the_provider_that_owns_the_market(): void
    {
        $service = app(TradingExecutionService::class);

        $this->assertInstanceOf(AlpacaStockBroker::class, $service->brokerFor('GLOBAL'));
        $this->assertInstanceOf(AlpacaStockBroker::class, $service->brokerFor('INTERNATIONAL'));
        $this->assertInstanceOf(CslStockBroker::class, $service->brokerFor('NGX'));

        // Crypto and Fixed Income are not stock markets and must never route here.
        $this->expectException(InvalidArgumentException::class);
        $service->brokerFor('CRYPTO');
    }

    public function test_global_stock_order_is_routed_to_alpaca_and_reserves_usd(): void
    {
        $user = $this->userWithWallets();
        $this->alpacaAccount($user);

        $order = app(TradingExecutionService::class)->submit($user, [
            'market' => 'GLOBAL',
            'symbol' => 'AAPL',
            'side' => 'buy',
            'quantity' => 2.5,
            'type' => 'market',
            'market_price' => 200,
            'company' => 'Apple Inc.',
        ]);

        $this->assertSame('alpaca', $order->provider);
        $this->assertSame('alpaca', $order->source);
        $this->assertSame('GLOBAL', $order->market);
        $this->assertSame('USD', $order->currency);
        $this->assertSame(2.5, (float) $order->quantity);
        $this->assertSame('open', $order->status);
        $this->assertSame(0.0, (float) $order->filled_quantity);
        $this->assertSame('pending', $order->reconciliation_status);

        $this->assertNotNull($order->provider_order_id);
        $this->assertStringStartsWith('XAV-', $order->provider_client_reference);
        $this->assertSame(
            $order->provider_client_reference,
            $order->provider_request['client_order_id']
        );
        $this->assertSame('ALP-ACC-1', $order->provider_market_account_id);

        // 2.5 x 200 = 500 reserved out of the USD wallet.
        $wallet = Wallet::query()->where('currency', 'USD')->firstOrFail();
        $this->assertSame(4500.0, (float) $wallet->usd_cleared);
        $this->assertSame(500.0, (float) $wallet->locked);

        // The NGN wallet is untouched by a Global trade.
        $ngn = Wallet::query()->where('currency', 'NGN')->firstOrFail();
        $this->assertSame(100000.0, (float) $ngn->ngn_cleared);
        $this->assertSame(0.0, (float) $ngn->locked);
    }

    public function test_fractional_quantity_is_refused_for_ngx_orders(): void
    {
        $user = $this->userWithWallets();

        try {
            app(TradingExecutionService::class)->submit($user, [
                'market' => 'NGX',
                'symbol' => 'TIP',
                'side' => 'buy',
                'quantity' => 1.5,
                'type' => 'limit',
                'limit_price' => 100,
                'market_price' => 100,
            ]);

            $this->fail('NGX stock must remain whole-share only.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        // Refused before the order or the reservation existed.
        $this->assertSame(0, Order::count());
        $this->assertSame(
            0.0,
            (float) Wallet::query()->where('currency', 'NGN')->firstOrFail()->locked
        );
    }

    public function test_global_order_is_refused_without_an_active_alpaca_account(): void
    {
        $user = $this->userWithWallets();

        try {
            app(TradingExecutionService::class)->submit($user, [
                'market' => 'GLOBAL',
                'symbol' => 'AAPL',
                'side' => 'buy',
                'quantity' => 1,
                'type' => 'market',
                'market_price' => 200,
            ]);

            $this->fail('A user without an Alpaca account must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No active ALPACA', $exception->getMessage());
        }

        $this->assertSame(0, Order::count());
    }

    public function test_global_order_cannot_spend_a_funded_ngn_wallet(): void
    {
        $user = $this->userWithWallets(usdCleared: 100);
        $this->alpacaAccount($user);

        try {
            app(TradingExecutionService::class)->submit($user, [
                'market' => 'GLOBAL',
                'symbol' => 'AAPL',
                'side' => 'buy',
                'quantity' => 2.5,
                'type' => 'market',
                'market_price' => 200,
            ]);

            $this->fail('An underfunded USD wallet must refuse the order.');
        } catch (\Exception $exception) {
            $this->assertSame(
                'Insufficient cleared funds.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(
            0.0,
            (float) Wallet::query()->where('currency', 'USD')->firstOrFail()->locked
        );
        $this->assertSame(
            100000.0,
            (float) Wallet::query()->where('currency', 'NGN')->firstOrFail()->ngn_cleared
        );
    }

    public function test_shared_alpaca_accounts_are_refused_by_portfolio_sync(): void
    {
        $first = $this->alpacaAccount($this->userWithWallets());
        $secondUser = $this->userWithWallets();
        $second = ProviderAccount::create([
            'user_id' => $secondUser->id,
            'provider' => 'alpaca',
            'market_id' => 'US1',
            'market_account_id' => 'ALP-ACC-2',
            'status' => 'active',
        ]);
        $shared = ['alpaca_account_id' => 'shared-paper-account'];

        $first->update(['metadata' => $shared]);
        $second->update(['metadata' => $shared]);
        $provider = Mockery::mock(\App\Providers\AlpacaProvider::class);
        $provider->shouldNotReceive('positions');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shared Alpaca accounts');

        (new \App\Services\Stocks\AlpacaPortfolioSyncService($provider))
            ->sync($first->fresh());
    }

    public function test_definitive_alpaca_rejection_releases_the_reservation(): void
    {
        config()->set('services.alpaca.mock', false);
        config()->set('services.alpaca.live_trading_enabled', true);
        Http::fake([
            '*/v2/orders' => Http::response([
                'message' => 'insufficient buying power',
            ], 422),
        ]);

        $user = $this->userWithWallets();
        $this->alpacaAccount($user);

        try {
            app(TradingExecutionService::class)->submit($user, [
                'market' => 'GLOBAL',
                'symbol' => 'AAPL',
                'side' => 'buy',
                'quantity' => 2.5,
                'type' => 'market',
                'market_price' => 200,
            ]);
            $this->fail('A definitive provider rejection must surface.');
        } catch (ProviderRequestException $exception) {
            $this->assertFalse($exception->ambiguous);
            $this->assertSame(422, $exception->statusCode);
        }

        $order = Order::where('provider', 'alpaca')->firstOrFail();
        $this->assertSame('rejected', $order->status);
        $this->assertSame('rejected', $order->reconciliation_status);
        $this->assertSame(0.0, (float) Wallet::where('currency', 'USD')->first()->locked);
        $this->assertSame(5000.0, (float) Wallet::where('currency', 'USD')->first()->usd_cleared);
    }

    private function userWithWallets(float $usdCleared = 5000): User
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

        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance' => $usdCleared,
            'usd_cleared' => $usdCleared,
            'usd_uncleared' => 0,
            'locked' => 0,
            'status' => 'active',
        ]);

        return $user;
    }

    private function alpacaAccount(User $user): ProviderAccount
    {
        return ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'alpaca',
            'market_id' => 'US1',
            'market_account_id' => 'ALP-ACC-1',
            'status' => 'active',
        ]);
    }
}
