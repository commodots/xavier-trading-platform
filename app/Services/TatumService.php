<?php

namespace App\Services;

use App\Models\CryptoAddress;
use Illuminate\Support\Facades\Http;

class TatumService
{
    public function generateTronAddress(int $userId): string
    {
        $res = Http::withHeaders([
            'x-api-key' => config('services.crypto.api_key'),
        ])->get(config('services.crypto.base_url').'/tron/address');

        $data = $res->json();

        $address = $data['address'];
        $privateKey = $data['privateKey'];
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($address);

        CryptoAddress::create([
            'user_id' => $userId,
            'blockchain' => 'TRON',
            'address' => $address,
            'private_key' => encrypt($privateKey),
            'qr_code_url' => $qrCodeUrl,
        ]);

        // Subscribe to webhook for deposit notifications
        $this->subscribeAddress($address);

        return $address;
    }

    public function subscribeAddress(string $address): void
    {
        Http::withHeaders([
            'x-api-key' => config('services.crypto.api_key'),
        ])->post(config('services.crypto.base_url').'/subscription', [
            'type' => 'ADDRESS_EVENT',
            'attr' => [
                'address' => $address,
                'chain' => 'TRON',
                'url' => config('app.url').'/api/crypto/webhook',
            ],
        ]);
    }

    public function withdraw(string $toAddress, float $amount, string $privateKey): array
    {
        $res = Http::withHeaders([
            'x-api-key' => config('services.crypto.api_key'),
        ])->post(config('services.crypto.base_url').'/tron/transaction', [
            'to' => $toAddress,
            'amount' => (string) $amount,
            'privateKey' => $privateKey,
        ]);

        return $res->json();
    }
}
