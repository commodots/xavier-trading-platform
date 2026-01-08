<?php

namespace App\Services\Crypto\Mock;

use App\Services\Crypto\Contracts\CryptoBroker;
use Illuminate\Support\Str;

class MockApintoService implements CryptoBroker
{
    public function placeOrder(array $data): array
    {
        return [
            'order_id' => 'CRYPTO_' . Str::random(8),
            'asset' => $data['asset'],
            'side' => $data['side'],
            'price' => rand(100, 60000),
            'status' => 'filled',
        ];
    }

    public function getPortfolio(int $userId): array
    {
        return [
            ['asset' => 'BTC', 'balance' => 0.25],
            ['asset' => 'ETH', 'balance' => 3.4],
        ];
    }
}
