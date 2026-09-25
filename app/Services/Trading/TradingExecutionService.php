<?php

namespace App\Services\Trading;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslStockBroker;
use App\Services\Stocks\AlpacaStockBroker;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Stocks\Exceptions\ProviderRequestException;
use App\Services\Stocks\Contracts\StockBrokerPreflight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TradingExecutionService
{
    /**
     * Optional explicitly-injected broker.
     *
     * Deliberately untyped: the container auto-resolves class-typed constructor
     * params from the global StockBroker binding, which would funnel every
     * market through one provider. Untyped means
     * `app(TradingExecutionService::class)` resolves per-market at runtime,
     * while tests can still inject via
     * `app(TradingExecutionService::class, ['broker' => $broker])`.
     *
     * @var StockBroker|null
     */
    public function __construct(
        protected $broker = null
    ) {}

    /**
     * Resolve the stock broker that owns a market.
     *
     * NGX -> CSL, GLOBAL/US/INTERNATIONAL -> Alpaca. Everything else is an
     * unsupported stock market; Crypto, Fixed Income and Demo must never reach
     * this service.
     */
    public function brokerFor(string $market): StockBroker
    {
        return match (strtoupper($market)) {
            'NGX' => app(CslStockBroker::class),

            'GLOBAL',
            'INTERNATIONAL',
            'US' => app(AlpacaStockBroker::class),

            default => throw new InvalidArgumentException(
                "Unsupported stock market: {$market}"
            ),
        };
    }

    public function submit(
        User $user,
        array $data
    ): Order {

        $data = $this->validateOrderData($data);

        $market = $data['market'];
        $currency = $this->currencyFor($market);
        $provider = $this->providerFor($market);
        $broker = $this->broker ?? $this->brokerFor($market);
        $account = $this->resolveProviderAccount($user, $provider);
        $clientReference = $this->clientReference();

        $providerData = [
            ...$data,
            'market_id' => $account->market_id,
            'market_account_id' => $account->market_account_id,
            'client_reference' => $clientReference,
            'xavier_client_reference' => $clientReference,
        ];

        if ($broker instanceof StockBrokerPreflight) {
            $broker->assertReadyForSubmission($providerData);
        }

        $reservation = null;

        /**
         * Persist the order intent and the local reservation BEFORE talking to
         * CSL. If the provider call times out we must still own an auditable
         * order record, otherwise a fill on CSL's side would have nothing to
         * reconcile against.
         */
        $order = DB::transaction(function () use (
            $user,
            $data,
            $account,
            $clientReference,
            $market,
            $currency,
            $provider,
            &$reservation
        ): Order {

            $reservation = $this->reserveLocalState($user, $data, $currency);

            return Order::create([
                'user_id' => $user->id,

                'symbol' => $data['symbol'],

                'side' => $data['side'],

                'type' => $data['type']
                    ?? 'market',

                'price' => $data['limit_price']
                    ?? $data['market_price']
                    ?? null,

                'quantity' => $data['quantity'],

                'units' => $data['quantity'],

                'amount' => $data['amount']
                    ?? (($data['quantity'] ?? 0) * ($data['limit_price']
                        ?? $data['market_price']
                        ?? 0)),

                'market_price' => $data['market_price']
                    ?? null,

                'company' => $data['company']
                    ?? $data['symbol'],

                /**
                 * Open, not filled: the provider has not accepted the order yet.
                 */
                'filled_quantity' => 0,

                'status' => 'open',

                'market' => $market,

                'currency' => $currency,

                'provider' => $provider,

                'provider_client_reference' => $clientReference,

                'provider_market_account_id' => $account->market_account_id,

                'time_in_force' => $data['time_in_force']
                    ?? 'DAY',

                'expiry_date' => $data['expiry_date']
                    ?? null,

                'provider_request' => [
                    'xavier_client_reference' => $clientReference,
                ],

                'provider_submitted_at' => now(),

                'reconciliation_status' => 'pending',

                'source' => $provider,
            ]);
        });

        $this->recordAudit($user->id, 'stock_order_created', $order, $provider);
        $this->recordAudit($user->id, 'provider_order_submitted', $order, $provider);

        try {
            $providerResult =
                $data['side'] === 'buy'
                ? $broker->buy($providerData)
                : $broker->sell($providerData);
        } catch (Throwable $exception) {
            $definitiveRejection = $exception instanceof ProviderRequestException
                && ! $exception->ambiguous;

            $order->update([
                'status' => $definitiveRejection ? 'rejected' : 'open',
                'reconciliation_status' => $definitiveRejection
                    ? 'rejected'
                    : 'unknown',
                'provider_response' => [
                    'error' => $exception->getMessage(),
                    'http_status' => $exception instanceof ProviderRequestException
                        ? $exception->statusCode
                        : null,
                ],
            ]);

            if ($definitiveRejection) {
                $this->releaseLocalState($reservation);
            }

            $this->recordAudit($user->id, 'provider_error', $order, $provider, [
                'exception' => $exception::class,
                'ambiguous' => ! $definitiveRejection,
            ]);

            throw $exception;
        }

        $providerStatus = $providerResult['status'] ?? 'pending';

        /**
         * Only a definitive refusal releases the reservation. An accepted or
         * pending order stays reserved until reconciliation reports execution
         * (or a confirmed cancellation).
         */
        if (in_array(
            $providerStatus,
            ['rejected', 'failed', 'canceled', 'cancelled'],
            true
        )) {
            $this->releaseLocalState($reservation);
        }

        $order->update([
            'status' => $this->mapStatus($providerStatus),

            'provider_order_id' => $providerResult['provider_order_id'] ?? null,

            'provider_request' => [
                ...($providerResult['request'] ?? []),
                'xavier_client_reference' => $clientReference,
            ],

            'provider_response' => $providerResult['response'] ?? null,
        ]);

        if (! in_array($providerStatus, ['rejected', 'failed'], true)) {
            $this->recordAudit(
                $user->id,
                'provider_order_accepted',
                $order,
                $provider
            );
        }

        return $order->fresh();
    }

    protected function validateOrderData(array $data): array
    {
        $market = strtoupper(trim((string) ($data['market'] ?? 'NGX')));
        $supportedMarkets = ['NGX', 'GLOBAL', 'INTERNATIONAL', 'US'];

        if (! in_array($market, $supportedMarkets, true)) {
            throw ValidationException::withMessages([
                'market' => 'Unsupported stock market.',
            ]);
        }

        $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));

        if ($symbol === '' || strlen($symbol) > 30) {
            throw ValidationException::withMessages([
                'symbol' => 'A stock symbol is required.',
            ]);
        }

        $side = strtolower(trim((string) ($data['side'] ?? '')));

        if (! in_array($side, ['buy', 'sell'], true)) {
            throw ValidationException::withMessages([
                'side' => 'Order side must be buy or sell.',
            ]);
        }

        $type = strtolower(trim((string) ($data['type'] ?? 'market')));
        $allowedTypes = $market === 'NGX'
            ? ['market', 'limit']
            : ['market', 'limit', 'stop', 'bracket'];

        if (! in_array($type, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported order type for this market.',
            ]);
        }

        $price = (float) ($data['limit_price'] ?? $data['market_price'] ?? 0);

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'price' => 'A positive market or limit price is required.',
            ]);
        }

        if ($type === 'limit' && (float) ($data['limit_price'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'limit_price' => 'A positive limit price is required.',
            ]);
        }

        if ($type === 'stop' && (float) ($data['stop_price'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'stop_price' => 'A positive stop price is required.',
            ]);
        }

        if ($type === 'bracket'
            && (! isset($data['take_profit'], $data['stop_loss']))) {
            throw ValidationException::withMessages([
                'type' => 'Bracket orders require take profit and stop loss.',
            ]);
        }

        $data['market'] = $market;
        $data['symbol'] = $symbol;
        $data['side'] = $side;
        $data['type'] = $type;
        $data['quantity'] = $this->normaliseQuantity($market, $data);

        return $data;
    }

    protected function clientReference(): string
    {
        // Alpaca limits client_order_id to 48 characters. Keep the readable
        // Xavier prefix and a 116-bit UUID prefix within that hard limit.
        return 'XAV-'.now()->format('YmdHis').'-'
            .Str::substr((string) Str::uuid(), 0, 29);
    }

    protected function recordAudit(
        int $userId,
        string $activity,
        Order $order,
        string $provider,
        array $metadata = []
    ): void {
        ActivityLog::log($userId, $activity, [
            'order_id' => $order->id,
            'provider' => $provider,
            'market' => $order->market,
            'symbol' => $order->symbol,
            'side' => $order->side,
            'status' => $order->status,
            'provider_client_reference' => $order->provider_client_reference,
            ...$metadata,
        ]);
    }

    /**
     * Trading currency for a market: NGX settles in NGN, Global in USD.
     */
    protected function currencyFor(string $market): string
    {
        return match (strtoupper($market)) {
            'NGX' => 'NGN',

            'GLOBAL',
            'INTERNATIONAL',
            'US' => 'USD',

            default => throw new InvalidArgumentException(
                "Unsupported stock market: {$market}"
            ),
        };
    }

    /**
     * The provider account provider key for a market.
     */
    protected function providerFor(string $market): string
    {
        return match (strtoupper($market)) {
            'NGX' => 'csl',

            'GLOBAL',
            'INTERNATIONAL',
            'US' => 'alpaca',

            default => throw new InvalidArgumentException(
                "Unsupported stock market: {$market}"
            ),
        };
    }

    /**
     * NGX trades whole shares only; Global markets may trade fractions.
     */
    protected function normaliseQuantity(string $market, array $data): int|float
    {
        $quantity = (float) ($data['quantity'] ?? 0);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if (strtoupper($market) === 'NGX'
            && $quantity !== floor($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'NGX stock quantity must be a whole number.',
            ]);
        }

        return strtoupper($market) === 'NGX'
            ? (int) $quantity
            : $quantity;
    }

    /**
     * Locate the provider-owned trading account for this user.
     */
    protected function resolveProviderAccount(
        User $user,
        string $provider
    ): ProviderAccount {

        $account = ProviderAccount::where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('status', 'active')
            ->first();

        if (! $account || blank($account->market_account_id)) {
            throw new RuntimeException(
                'No active '.strtoupper($provider)
                .' trading account with a provider account ID is mapped to this user.'
            );
        }

        return $account;
    }

    protected function reserveLocalState(
        User $user,
        array $data,
        string $currency = 'NGN'
    ): array {
        $quantity = (float) $data['quantity'];
        $price = (float) ($data['limit_price'] ?? $data['market_price']);
        $amount = $quantity * $price;

        if ($data['side'] === 'buy') {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw ValidationException::withMessages([
                    'wallet' => "A {$currency} wallet is required for this order.",
                ]);
            }

            $wallet->reserve($amount);

            return [
                'side' => 'buy',
                'wallet_id' => $wallet->id,
                'amount' => $amount,
            ];
        }

        $portfolio = Portfolio::query()
            ->where('user_id', $user->id)
            ->where('symbol', $data['symbol'])
            ->lockForUpdate()
            ->first();

        if (! $portfolio || (float) $portfolio->cleared_quantity < $quantity) {
            throw ValidationException::withMessages([
                'portfolio' => 'Insufficient cleared holdings.',
            ]);
        }

        $portfolio->decrement('cleared_quantity', $quantity);
        $portfolio->increment('uncleared_quantity', $quantity);

        return [
            'side' => 'sell',
            'portfolio_id' => $portfolio->id,
            'quantity' => $quantity,
        ];
    }

    protected function releaseLocalState(array $reservation): void
    {
        if ($reservation['side'] === 'buy') {
            Wallet::query()->findOrFail($reservation['wallet_id'])
                ->releaseReservation($reservation['amount']);

            return;
        }

        $portfolio = Portfolio::query()->findOrFail($reservation['portfolio_id']);
        $portfolio->decrement('uncleared_quantity', $reservation['quantity']);
        $portfolio->increment('cleared_quantity', $reservation['quantity']);
    }

    protected function mapStatus(
        string $status
    ): string {

        return match (strtolower(trim($status))) {
            'accepted' => 'open',
            'pending' => 'pending',
            'partially_filled' => 'partially_filled',
            'filled' => 'filled',
            'cancel_requested' => 'cancel_requested',
            'canceled', 'cancelled' => 'canceled',
            'rejected' => 'rejected',
            'failed' => 'failed',
            default => 'open',
        };
    }
}
