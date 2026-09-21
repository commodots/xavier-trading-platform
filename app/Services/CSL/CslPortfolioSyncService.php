<?php

namespace App\Services\CSL;

use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\Symbol;
use Illuminate\Support\Facades\DB;

class CslPortfolioSyncService
{
    public function __construct(
        protected CslStockClient $st
    ) {}

    public function sync(
        ProviderAccount $account
    ): int {
        return DB::transaction(function () use ($account): int {

            if (! $account->market_account_id) {
                throw new \RuntimeException(
                    'CSL market account ID is missing.'
                );
            }

            $response = $this->st->stockPortfolio(
                $account->market_account_id
            );

            $rows = $this->extractRows($response);

            /**
             * Never zero the local portfolio if CSL returned an empty
             * response. Empty can indicate an API/data problem.
             */
            if ($rows === []) {
                return 0;
            }

            $symbols = [];
            $count = 0;

            foreach ($rows as $row) {

                $providerSymbolId = trim((string) (
                    $row['symbol_identifier']
                    ?? $row['symbol_id']
                    ?? ''
                ));

                if ($providerSymbolId === '') {
                    continue;
                }

                $symbolModel = Symbol::query()
                    ->where('provider', 'csl')
                    ->where(
                        'provider_symbol_id',
                        $providerSymbolId
                    )
                    ->first();

                $symbol = strtoupper(
                    $symbolModel?->symbol
                    ?? $row['symbol_code']
                    ?? $providerSymbolId
                );

                $symbols[] = $symbol;

                $quantity = $this->number(
                    $row['unit_quantity']
                    ?? $row['current_quantity']
                    ?? $row['quantity']
                    ?? 0
                );

                $averagePrice = $this->number(
                    $row['average_cost_price']
                    ?? $row['average_price']
                    ?? $row['cost_price']
                    ?? 0
                );

                $marketPrice = $this->number(
                    $row['market_price']
                    ?? $row['current_price']
                    ?? $row['price']
                    ?? 0
                );

                Portfolio::updateOrCreate(
                    [
                        'user_id' => $account->user_id,
                        'symbol' => $symbol,
                    ],
                    [
                        'name' => $row['symbol_description']
                            ?? $symbol,

                        'category' => 'local',

                        'currency' => $row['currency_description']
                            ?? 'NGN',

                        'quantity' => $quantity,

                        /**
                         * Initial provider snapshot.
                         * Reconciliation remains responsible for
                         * pending/uncleared Xavier transactions.
                         */
                        'cleared_quantity' => $quantity,

                        'uncleared_quantity' => 0,

                        'avg_price' => $averagePrice,

                        'market_price' => $marketPrice,
                    ]
                );

                $count++;
            }

            /**
             * Only remove stale local holdings after a successful,
             * non-empty provider snapshot.
             */
            if ($symbols !== []) {
                Portfolio::query()
                    ->where('user_id', $account->user_id)
                    ->where('category', 'local')
                    ->whereNotIn('symbol', $symbols)
                    ->update([
                        'quantity' => 0,
                        'cleared_quantity' => 0,
                        'uncleared_quantity' => 0,
                    ]);
            }

            return $count;
        });
    }

    protected function number($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value)
            ? (float) $value
            : 0;
    }

    protected function extractRows(array $response): array
    {
        if (
            isset($response['result'][0]['GetStockPortfolio'])
            && is_array($response['result'][0]['GetStockPortfolio'])
        ) {
            return $response['result'][0]['GetStockPortfolio'];
        }

        if (
            isset($response['GetStockPortfolio'])
            && is_array($response['GetStockPortfolio'])
        ) {
            return $response['GetStockPortfolio'];
        }

        if (
            isset($response['result'][0]['GetCRSTStockPortfolio'])
            && is_array($response['result'][0]['GetCRSTStockPortfolio'])
        ) {
            return $response['result'][0]['GetCRSTStockPortfolio'];
        }

        if (
            isset($response['GetCRSTStockPortfolio'])
            && is_array($response['GetCRSTStockPortfolio'])
        ) {
            return $response['GetCRSTStockPortfolio'];
        }

        return [];
    }
}
