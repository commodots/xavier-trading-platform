<?php

namespace App\Services\CSL;

use App\Models\Symbol;

class CslInstrumentService
{
    public function __construct(
        protected CslStockClient $st
    ) {}

    public function sync(): int
    {
        $response = $this->st->instruments();

        $rows = $this->extractRows($response);

        $count = 0;

        foreach ($rows as $row) {

            $symbol = $row['symbol']
                ?? $row['symbol_code']
                ?? null;

            if (! $symbol) {
                continue;
            }

            Symbol::updateOrCreate(
                [
                    'provider' => 'csl',
                    'provider_symbol_id' => $row['symbol_id'] ?? $symbol,
                ],
                [
                    'symbol' => strtoupper($symbol),

                    'name' => $row['symbol_description']
                        ?? $row['description']
                        ?? $symbol,

                    'type' => $row['symbol_type']
                        ?? 'equity',

                    'exchange' => $row['market']
                        ?? 'NGX',

                    'last_price' => $row['current_price']
                        ?? null,

                    'provider' => 'csl',

                    'provider_symbol_id' => $row['symbol_id'] ?? $symbol,

                    'market_id' => $row['market_id']
                        ?? $row['market']
                        ?? null,

                    'product_id' => $row['product_id']
                        ?? null,

                    'isin' => $row['isin_identifier']
                        ?? null,

                    'provider_symbol_type' => $row['symbol_type']
                        ?? null,

                    'provider_metadata' => $row,
                ]
            );

            $count++;
        }

        return $count;
    }

    protected function extractRows(array $response): array
    {
        foreach ([
            'GetCRSTInstruments',
            'instruments',
            'result',
            'data',
        ] as $key) {

            if (
                isset($response[$key])
                && is_array($response[$key])
            ) {
                return $response[$key];
            }
        }

        return [];
    }
}
