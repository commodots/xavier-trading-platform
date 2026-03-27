<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;

class MarketService
{
    public function getPrices()
    {
        return cache()->remember('crypto_prices', 10, function () {
            $res = Http::get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => 'bitcoin,ethereum,tether',
                'vs_currencies' => 'usd',
            ]);

            return $res->json();
        });
    }

    public function getBTCPrice()
    {
        return $this->getPrices()['bitcoin']['usd'];
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
