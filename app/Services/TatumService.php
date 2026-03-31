<?php

namespace App\Services;

use App\Models\CryptoAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class TatumService
{
    public function generateTronAddress(int $userId): string
{
    try {
        $res = Http::withHeaders([
            'x-api-key' => config('services.crypto.api_key'),
        ])->get(config('services.crypto.base_url').'/tron/address');
        
        // This only catches 4xx and 5xx errors
        if ($res->failed()) {
            throw new \Exception("Tatum API failed: " . $res->body());
        }

        $data = $res->json();

        // --- ADD THIS CHECK BELOW ---
        if (!isset($data['address']) || !isset($data['privateKey'])) {
            // Log the actual response so you can see why it's failing on the server
            Log::error("Tatum Response Error for User $userId", ['response' => $data]);
            throw new \Exception("Tatum returned a success status but no address data was found.");
        }

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

        $this->subscribeAddress($address);

        return $address;

    } catch (ConnectionException $e) {
        Log::error("Tatum API Connection Error: " . $e->getMessage());
        throw new \Exception("Unable to reach the crypto service.");
    }
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

        if ($res->failed()) {
            throw new \Exception("Tatum withdrawal failed: " . $res->body());
        }

        return $res->json();
    }
}
