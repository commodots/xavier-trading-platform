<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\SystemSetting;
use App\Providers\AlpacaProvider;
use App\Providers\FinnhubProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketService
{
    public function getProvider(): object
    {
        return match (strtolower(env('MARKET_PROVIDER', 'finnhub'))) {
            'alpaca' => new AlpacaProvider,
            default => new FinnhubProvider,
        };
    }

    public function quote(string $symbol): float
    {
        $symbol = strtoupper($symbol);

        $price = (float) $this->getProvider()->quote($symbol);
        if ($price > 0) {
            return $price;
        }

        $localPrice = (float) Symbol::where('symbol', $symbol)->value('last_price');
        if ($localPrice > 0) {
            return $localPrice;
        }

        if ($this->isCrypto($symbol)) {
            $prices = $this->getPrices();
            $cryptoPrice = $this->lookupCryptoPrice($symbol, $prices);

            if ($cryptoPrice > 0) {
                return $cryptoPrice;
            }
        }

        return 0.0;
    }

    private function isCrypto(string $symbol): bool
    {
        return in_array($symbol, $this->getCryptoSymbols(), true) || str_contains($symbol, '/USDT');
    }

    public function quoteDetails(string $symbol): array
    {
        $provider = $this->getProvider();

        if (method_exists($provider, 'quoteDetails')) {
            return $provider->quoteDetails($symbol);
        }

        $price = $this->quote($symbol);

        return [
            'symbol' => strtoupper($symbol),
            'price' => $price,
            'change' => 0.0,
            'previous_close' => 0.0,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function getPrices(): array
    {
        return cache()->remember('crypto_prices', 300, function () {
            try {
                $res = Http::timeout(10)->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'bitcoin,ethereum,tether,binancecoin,solana,ripple,cardano,dogecoin,polkadot,tron,chainlink,matic-network',
                    'vs_currencies' => 'usd',
                ]);

                if ($res->successful()) {
                    return $res->json();
                }
            } catch (\Exception $e) {
                Log::warning('CoinGecko API unavailable: '.$e->getMessage());
            }

            // Fallback: If the API fails, return last-known cached prices to prevent stale execution
            $cached = cache()->get('crypto_prices_last_known');
            if ($cached) {
                Log::warning('CoinGecko API unavailable, using last-known cached prices');
                return $cached;
            }

            // If no cached prices exist at all, return empty array to signal prices are unavailable.
            // Callers must check for empty prices and refuse to execute trades.
            Log::error('CoinGecko API unavailable and no cached prices exist');
            return [];
        });
    }

    public function getBTCPrice()
    {
        return $this->getPrices()['bitcoin']['usd'] ?? 64000.00;
    }

    public function getProviderName(): string
    {
        return strtolower(env('MARKET_PROVIDER', 'finnhub'));
    }

    public function applySpread(float $price, string $side): float
    {
        $settings = SystemSetting::first();
        $spread = (float) ($settings->crypto_spread ?? 0);

        if ($side === 'buy') {
            return $price * (1 + $spread / 100);
        }

        if ($side === 'sell') {
            return $price * (1 - $spread / 100);
        }

        return $price;
    }

    public function getCryptoFee(float $amount): float
    {
        $settings = SystemSetting::first();
        $feePercent = (float) ($settings->crypto_fee ?? 0);

        return $amount * $feePercent / 100;
    }

    /**
     * Get list of supported crypto symbols
     */
    public function getCryptoSymbols(): array
    {
        return ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'XRP', 'ADA', 'DOGE', 'DOT', 'TRX', 'LINK', 'MATIC'];
    }

    /**
     * Check if a symbol or pair is cryptocurrency
     */
    public function isCryptoPair(string $symbolOrPair): bool
    {
        $symbol = strtoupper(explode('/', $symbolOrPair)[0]);
        return in_array($symbol, $this->getCryptoSymbols(), true) || str_contains($symbolOrPair, '/USDT');
    }

    /**
     * Lookup crypto price by symbol from prices array
     */
    public function lookupCryptoPrice(string $symbol, array $prices): float
    {
        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'USDT' => 'tether',
            'BNB' => 'binancecoin',
            'SOL' => 'solana',
            'XRP' => 'ripple',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'TRX' => 'tron',
            'LINK' => 'chainlink',
            'MATIC' => 'matic-network',
        ];

        $id = $map[strtoupper($symbol)] ?? null;
        return $id ? (float) ($prices[$id]['usd'] ?? 0.0) : 0.0;
    }
}
