<?php

namespace App\Services\Trading;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Stocks\Contracts\StockBroker;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TradingExecutionService
{
    public function __construct(
        protected StockBroker $broker
    ) {}

    public function submit(
        User $user,
        array $data
    ): Order {

        $account = ProviderAccount::where(
            'user_id',
            $user->id
        )
            ->where(
                'provider',
                'csl'
            )
            ->where(
                'status',
                'active'
            )
            ->first();

        if (! $account) {
            throw new RuntimeException(
                'No active CSL trading account is mapped to this user.'
            );
        }

        $clientReference = 'XAV-'.now()->format('YmdHis').'-'.str()->uuid();

        $reservation = null;

        /*
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
            &$reservation
        ): Order {

            $reservation = $this->reserveLocalState($user, $data);

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

                /*
                 * Open, not filled: the provider has not accepted the order yet.
                 */
                'filled_quantity' => 0,

                'status' => 'open',

                'market' => 'NGX',

                'currency' => 'NGN',

                'provider' => 'csl',

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

                'source' => 'csl',
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
                ? $this->broker->buy($providerData)
                : $this->broker->sell($providerData);
        } catch (Throwable $exception) {
            /*
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

        if (! in_array($providerStatus, ['accepted', 'pending'], true)) {
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

    protected function reserveLocalState(User $user, array $data): array
    {
        $quantity = (float) $data['quantity'];
        $price = (float) ($data['limit_price'] ?? $data['market_price']);
        $amount = $quantity * $price;

        if ($data['side'] === 'buy') {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('currency', 'NGN')
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

            'filled' => 'filled',

            default => 'canceled',
        };
    }
}
