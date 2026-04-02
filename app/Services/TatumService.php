<?php

namespace App\Services;

use App\Models\CryptoAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TatumService
{
    public function generateTronAddress($userId)
    {
        // Use the wallet endpoint to get mnemonic and xpub
        $res = Http::withHeaders([
            'x-api-key' => config('services.tatum.api_key'),
        ])->get(config('services.tatum.base_url').'/tron/wallet');

        $data = $res->json();

        if ($res->failed() || ! isset($data['xpub'])) {
            Log::error('Tatum Generation Failed', ['user_id' => $userId, 'status' => $res->status(), 'data' => $data, 'url' => config('services.tatum.base_url').'/tron/wallet']);

            throw new \Exception('Tatum API error: Unable to generate wallet');
        }

        $xpub = $data['xpub'];

        // Generate address from xpub at index 0
        $addressRes = Http::withHeaders([
            'x-api-key' => config('services.tatum.api_key'),
        ])->get(config('services.tatum.base_url').'/tron/address/'.$xpub.'/0');

        $addressData = $addressRes->json();

        if ($addressRes->failed() || ! isset($addressData['address'])) {
            Log::error('Tatum Address Generation Failed', ['user_id' => $userId, 'status' => $addressRes->status(), 'data' => $addressData, 'xpub' => $xpub]);

            throw new \Exception('Tatum API error: Unable to generate address');
        }

        $address = $addressData['address'];

        // For private key, we store the mnemonic encrypted (dangerous but for demo)
        // In production, you should not store private keys
        $mnemonic = $data['mnemonic'];

        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($address);

        // Save to database and encrypt the mnemonic for security (not recommended)
        CryptoAddress::create([
            'user_id' => $userId,
            'blockchain' => 'TRON',
            'address' => $address,
            'private_key' => encrypt($mnemonic), // Storing mnemonic instead of private key
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
