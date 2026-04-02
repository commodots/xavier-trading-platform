<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketService
{
    public function getPrices()
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

            // Fallback: If the API fails, return a basic structure to prevent "Undefined key" errors
            return [
                'bitcoin' => ['usd' => 64000],
                'ethereum' => ['usd' => 3400],
                'tether' => ['usd' => 1.00],
                'binancecoin' => ['usd' => 300],
                'solana' => ['usd' => 145],
                'ripple' => ['usd' => 0.50],
                'cardano' => ['usd' => 0.40],
                'dogecoin' => ['usd' => 0.10],
                'polkadot' => ['usd' => 5.00],
                'tron' => ['usd' => 0.12],
                'chainlink' => ['usd' => 12.00],
                'matic-network' => ['usd' => 0.80],
            ];
        });
    }

    public function getBTCPrice()
    {
        return $this->getPrices()['bitcoin']['usd'] ?? 64000.00;
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
}
