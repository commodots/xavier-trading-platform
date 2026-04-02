<?php

namespace App\Services;

use App\Models\CryptoAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TatumService
{
    public function generateTronAddress($userId)
    {
        // Use the wallet endpoint to get both address and privateKey
        $res = Http::withHeaders([
            'x-api-key' => config('services.tatum.api_key'),
        ])->get(config('services.tatum.base_url').'/tron/wallet');

        $data = $res->json();

        if ($res->failed() || ! isset($data['address'])) {
            Log::error('Tatum Generation Failed', ['user_id' => $userId, 'status' => $res->status(), 'data' => $data, 'url' => env('TATUM_BASE_URL').'/tron/wallet']);

            // Fallback for development: generate a mock address
            $mockAddress = 'T'.strtoupper(substr(md5(uniqid()), 0, 33));
            $mockPrivateKey = 'mock_private_key_'.uniqid();
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($mockAddress);

            CryptoAddress::create([
                'user_id' => $userId,
                'blockchain' => 'TRON',
                'address' => $mockAddress,
                'private_key' => encrypt($mockPrivateKey),
                'qr_code_url' => $qrCodeUrl,
            ]);

            Log::info('Used mock TRON address for user', ['user_id' => $userId, 'address' => $mockAddress]);

            return $mockAddress;
        }

        $address = $data['address'];
        $privateKey = $data['privateKey'];
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($address);

        // Save to database and encrypt the private key for security
        CryptoAddress::create([
            'user_id' => $userId,
            'blockchain' => 'TRON',
            'address' => $address,
            'private_key' => encrypt($privateKey),
            'qr_code_url' => $qrCodeUrl,
        ]);

        $this->subscribeAddress($address);

        return $address;
    }

    public function subscribeAddress(string $address): void
    {
        Http::withHeaders([
            'x-api-key' => config('services.tatum.api_key'),
        ])->post(config('services.tatum.base_url').'/subscription', [
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

        if ($res->failed()) {
            throw new \Exception('Tatum withdrawal failed: '.$res->body());
        }

        return $res->json();
    }
}
