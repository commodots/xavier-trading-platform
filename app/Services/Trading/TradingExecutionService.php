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

class TradingExecutionService
{
    public function __construct(
        protected StockBroker $broker
    ) {}

    public function submit(
        User $user,
        array $data
    ): Order {

        return DB::transaction(function () use (
            $user,
            $data
        ) {

            $account =
                ProviderAccount::where(
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

            $reservation = $this->reserveLocalState($user, $data);

            $providerResult =
                $data['side'] === 'buy'
                    ? $this->broker->buy([
                        ...$data,

                        'market_id' => $account->market_id,

                        'market_account_id' => $account->market_account_id,
                        'xavier_client_reference' => $clientReference,
                    ])
                    : $this->broker->sell([
                        ...$data,

                        'market_id' => $account->market_id,

                        'market_account_id' => $account->market_account_id,
                        'xavier_client_reference' => $clientReference,
                    ]);

            $providerStatus = $providerResult['status'] ?? 'pending';

            if (! in_array($providerStatus, ['accepted', 'pending'], true)) {
                $this->releaseLocalState($reservation);
            }

            $order = Order::create([
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

                'filled_quantity' => 0,

                'status' => $this->mapStatus(
                    $providerResult['status']
                    ?? 'pending'
                ),

                'market' => 'NGX',

                'currency' => 'NGN',

                'provider' => 'csl',

                'provider_order_id' => $providerResult[
                        'provider_order_id'
                    ] ?? null,

                'provider_client_reference' => $clientReference,

                'provider_market_account_id' => $account->market_account_id,

                'time_in_force' => $data['time_in_force']
                    ?? 'DAY',

                'expiry_date' => $data['expiry_date']
                    ?? null,

                'provider_request' => [
                    ...($providerResult['request'] ?? []),
                    'xavier_client_reference' => $clientReference,
                ],

                'provider_response' => $providerResult['response']
                    ?? null,

                'provider_submitted_at' => now(),

                'reconciliation_status' => 'pending',

                'source' => 'csl',
            ]);

            return $order;
        });
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
