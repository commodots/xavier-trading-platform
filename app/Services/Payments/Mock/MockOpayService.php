<?php

namespace App\Services\Payments\Mock;

use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Support\Str;

class MockOpayService implements PaymentGateway
{
    public function transfer(array $data): array
    {
        return [
            'reference' => 'OPAY_' . Str::random(10),
            'status' => collect(['success', 'pending', 'failed'])->random(),
            'amount' => $data['amount'],
            'currency' => 'NGN',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function createVirtualAccount(array $data): array
    {
        return [
            'account_number' => '2309' . rand(100000, 999999),
            'bank' => 'OPay',
            'account_name' => $data['name'] ?? 'Mock User',
            'status' => 'ACTIVE',
        ];
    }

    public function getBalance(): array
    {
        return [
            'currency' => 'NGN',
            'balance' => rand(1_000_000, 50_000_000),
        ];
    }
}
