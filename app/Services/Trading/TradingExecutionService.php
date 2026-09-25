<?php

namespace App\Services\Trading;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CSL\CslStockBroker;
use App\Services\Stocks\AlpacaStockBroker;
use App\Services\Stocks\Contracts\StockBroker;
use Illuminate\Support\Facades\DB;
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

        $market = strtoupper($data['market'] ?? 'NGX');
        $currency = $this->currencyFor($market);
        $provider = $this->providerFor($market);

        $data['quantity'] = $this->normaliseQuantity($market, $data);

        $broker = $this->broker ?? $this->brokerFor($market);

        $account = $this->resolveProviderAccount($user, $provider);

        $clientReference = 'XAV-'.now()->format('YmdHis').'-'.str()->uuid();

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

        $providerData = [
            ...$data,

            'market_id' => $account->market_id,

            'market_account_id' => $account->market_account_id,

            'client_reference' => $clientReference,
            'xavier_client_reference' => $clientReference,
        ];

        try {
            $providerResult =
                $data['side'] === 'buy'
                ? $broker->buy($providerData)
                : $broker->sell($providerData);
        } catch (Throwable $exception) {
            /**
             * Timeout, connection reset or 5xx: the request may or may not have
             * reached CSL. Never retry blindly and never release the
             * reservation - reconciliation has to establish what happened.
             */
            $order->update([
                'reconciliation_status' => 'unknown',
                'provider_response' => [
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }

        $providerStatus = $providerResult['status'] ?? 'pending';

        /*
         * Only a definitive refusal releases the reservation. An accepted or
         * pending order stays reserved until reconciliation reports execution
         * (or a confirmed cancellation).
         */
        if (in_array($providerStatus, ['rejected', 'canceled', 'cancelled'], true)) {
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

        return $order->fresh();
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
    protected function normaliseQuantity(string $market, array $data): float
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

        return $quantity;
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

        if (! $account) {
            throw new RuntimeException(
                'No active '.strtoupper($provider)
                .' trading account is mapped to this user.'
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
                ->firstOrFail();

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
            throw new RuntimeException('Insufficient cleared holdings.');
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

        return match ($status) {
            'accepted',
            'pending' => 'open',

            'partially_filled' => 'partially_filled',

            'filled' => 'filled',

            'canceled',
            'cancelled',
            'rejected' => 'canceled',

            default => 'open',
        };
    }
}
