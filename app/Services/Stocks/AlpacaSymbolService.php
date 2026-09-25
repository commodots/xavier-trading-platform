<?php

namespace App\Services\Stocks;

use App\Models\Symbol;

/**
 * Store Global market symbols under the alpaca provider key.
 *
 * provider_symbol_id is the Alpaca ticker, which keeps Global symbols
 * distinct from CSL (provider = csl) rows in the same table.
 */
class AlpacaSymbolService
{
    /**
     * Upsert one symbol from an Alpaca asset / quote payload.
     */
    public function upsert(array $payload): ?Symbol
    {
        $symbol = strtoupper(trim((string) (
            $payload['symbol']
            ?? $payload['ticker']
            ?? ''
        )));

        if ($symbol === '') {
            return null;
        }

        return Symbol::updateOrCreate(
            [
                'provider' => 'alpaca',
                'provider_symbol_id' => $symbol,
            ],
            [
                'symbol' => $symbol,

                'name' => $payload['name']
                    ?? $payload['description']
                    ?? $symbol,

                'type' => $payload['type'] ?? 'equity',

                'exchange' => $payload['exchange']
                    ?? $payload['exchange_id']
                    ?? 'US',

                'last_price' => isset($payload['price'])
                    && is_numeric($payload['price'])
                    ? (float) $payload['price']
                    : null,

                'provider_metadata' => $payload,
            ]
        );
    }

    /**
     * Bulk upsert from an Alpaca assets listing.
     */
    public function sync(array $assets): int
    {
        $count = 0;

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            if ($this->upsert($asset)) {
                $count++;
            }
        }

        return $count;
    }
}
