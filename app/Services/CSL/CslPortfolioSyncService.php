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

    public function sync(ProviderAccount $account): int
    {
        return DB::transaction(function () use ($account): int {

            $response = $this->st->stockPortfolio(
                $account->market_account_id
            );

            $rows = $this->extractRows($response);

            $symbols = [];
            $count = 0;

            foreach ($rows as $row) {

                $providerSymbol = trim((string) (
                    $row['symbol_identifier']
                    ?? $row['symbol_id']
                    ?? ''
                ));

                if ($providerSymbol === '') {
                    continue;
                }

                /**
                 * Prefer the Xavier symbol already mapped to CSL.
                 */
                $symbolModel = Symbol::query()
                    ->where('provider', 'csl')
                    ->where('provider_symbol_id', $providerSymbol)
                    ->first();

                $symbol = strtoupper(
                    $symbolModel?->symbol
                    ?? $row['symbol_code']
                    ?? $providerSymbol
                );

                $symbols[] = $symbol;

                $quantity = $this->number(
                    $row['unit_quantity']
                    ?? $row['current_quantity']
                    ?? 0
                );

                $averagePrice = $this->number(
                    $row['average_cost_price']
                    ?? $row['average_price']
                    ?? 0
                );

                $marketPrice = $this->number(
                    $row['market_price']
                    ?? $row['current_price']
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
                         * Do not invent cleared quantities from CSL
                         * unless CSL explicitly supplies them.
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
             * Only zero Xavier CSL/local holdings after a successful
             * CSL response. Never clear the portfolio because of an
             * empty/failed response.
             */
            if ($rows !== []) {
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

        return [];
    }
}
