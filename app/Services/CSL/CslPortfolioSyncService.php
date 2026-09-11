<?php

namespace App\Services\CSL;

use App\Models\Portfolio;
use App\Models\ProviderAccount;
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
            $response = $this->st->stockPortfolio(
                $account->market_account_id
            );

            $rows = $this->extractRows($response);
            $symbols = [];
            $count = 0;

            foreach ($rows as $row) {
                $symbol = strtoupper((string) ($row['symbol'] ?? $row['symbol_code'] ?? ''));

                if ($symbol === '') {
                    continue;
                }

                $symbols[] = $symbol;

                Portfolio::updateOrCreate(
                    [
                        'user_id' => $account->user_id,
                        'symbol' => $symbol,
                    ],
                    [
                        'name' => $row['symbol_description'] ?? null,
                        'category' => 'local',
                        'currency' => 'NGN',
                        'quantity' => $row['quantity'] ?? $row['holding_quantity'] ?? 0,
                        'cleared_quantity' => $row['cleared_quantity'] ?? 0,
                        'uncleared_quantity' => $row['uncleared_quantity'] ?? 0,
                        'avg_price' => $row['average_price'] ?? 0,
                        'market_price' => $row['market_price'] ?? 0,
                    ]
                );

                $count++;
            }

            $staleHoldings = Portfolio::query()
                ->where('user_id', $account->user_id)
                ->where('category', 'local');

            if ($symbols !== []) {
                $staleHoldings->whereNotIn('symbol', $symbols);
            }

            $staleHoldings->update([
                'quantity' => 0,
                'cleared_quantity' => 0,
                'uncleared_quantity' => 0,
            ]);

            return $count;
        });
    }

    protected function extractRows(array $response): array
    {
        foreach ([
            'GetCRSTStockPortfolio',
            'portfolio',
            'result',
            'data',
        ] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }
}
