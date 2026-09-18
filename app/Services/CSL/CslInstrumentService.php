<?php

namespace App\Services\CSL;

use App\Models\Symbol;
use Illuminate\Support\Facades\DB;

class CslInstrumentService
{
    public function __construct(
        protected CslStockClient $st
    ) {}

    public function sync(): int
    {
        $response = $this->st->instruments();

        $rows = $this->extractRows($response);

        if ($rows === []) {
            throw new \RuntimeException(
                'CSL returned no instrument rows.'
            );
        }

        $count = 0;

        DB::transaction(function () use ($rows, &$count) {
            foreach ($rows as $row) {

                $providerSymbolId = trim((string) (
                    $row['symbol_id']
                    ?? $row['symbol_identifier']
                    ?? ''
                ));

                if ($providerSymbolId === '') {
                    continue;
                }

                /**
                 * CSL ST does not necessarily call this field "symbol".
                 * Prefer the actual symbol/ticker if returned, otherwise
                 * retain symbol_id as the fallback.
                 */
                $symbol = strtoupper(trim((string) (
                    $row['symbol_code']
                    ?? $row['symbol']
                    ?? $row['ticker']
                    ?? $providerSymbolId
                )));

                if ($symbol === '') {
                    continue;
                }

                Symbol::updateOrCreate(
                    [
                        'provider' => 'csl',
                        'provider_symbol_id' => $providerSymbolId,
                    ],
                    [
                        'symbol' => $symbol,

                        'name' => $row['symbol_description']
                            ?? $row['description']
                            ?? $symbol,

                        'type' => $row['symbol_type']
                            ?? 'equity',

                        'exchange' => $row['market_description']
                            ?? $row['market']
                            ?? 'NGX',

                        'last_price' => $this->number(
                            $row['current_price']
                            ?? $row['opening_price']
                            ?? null
                        ),

                        'market_id' => isset($row['market_id'])
                            ? (string) $row['market_id']
                            : null,

                        'product_id' => isset($row['product_id'])
                            ? (string) $row['product_id']
                            : null,

                        'isin' => $row['isin_identifier']
                            ?? null,

                        'provider_symbol_type' => $row['symbol_type']
                            ?? null,

                        'provider_metadata' => $row,
                    ]
                );

                $count++;
            }
        });

        return $count;
    }

    protected function number($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? (float) $value
            : null;
    }

    protected function extractRows(array $response): array
    {
        if (
            isset($response['result'][0]['GetCRSTInstruments'])
            && is_array($response['result'][0]['GetCRSTInstruments'])
        ) {
            return $response['result'][0]['GetCRSTInstruments'];
        }

        if (
            isset($response['GetCRSTInstruments'])
            && is_array($response['GetCRSTInstruments'])
        ) {
            return $response['GetCRSTInstruments'];
        }

        return [];
    }
}
