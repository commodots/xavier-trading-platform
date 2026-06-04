<?php

namespace App\Services;

use App\Models\KycVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DojahService
{
    private function headers(): array
    {
        $appId  = config('services.dojah.app_id');
        $secret = config('services.dojah.secret_key');

        if (empty($appId) || empty($secret)) {
            throw new \RuntimeException('Dojah properties are missing in config path maps.');
        }

        return [
            'AppId'         => $appId,
            'Authorization' => $secret,
            'Accept'        => 'application/json',
        ];
    }

    private function url(string $path): string
    {
        return rtrim(config('services.dojah.base_url', 'https://api.dojah.io'), '/') . $path;
    }

    public function verifyBvn(string $bvn): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->get($this->url('/api/v1/kyc/bvn'), ['bvn' => $bvn]);

            return $this->parse($response);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'BVN endpoint connection failure.'];
        }
    }

    public function verifyNin(string $nin): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->get($this->url('/api/v1/kyc/nin'), ['nin' => $nin]);

            return $this->parse($response);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'NIN endpoint connection failure.'];
        }
    }

    public function checkLiveness(string $base64Image): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($this->url('/api/v1/kyc/selfie'), ['image' => $base64Image]);

            return $this->parse($response);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Liveness validation gateway timed out.'];
        }
    }

    public function storeResult(int $userId, string $type, array $result): KycVerification
    {
        $status = ($result['success'] ?? false) ? 'approved' : 'failed';

        return KycVerification::updateOrCreate(
            ['user_id' => $userId, 'verification_type' => $type],
            [
                'verification_id' => $result['entity']['id'] ?? $result['entity']['reference_id'] ?? null,
                'status'          => $status,
                'response_json'   => json_encode($result),
            ]
        );
    }

    private function parse($response): array
    {
        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('error') ?? $response->json('message') ?? 'Dojah gateway response error.',
            ];
        }

        $data = $response->json();
        return is_array($data) ? array_merge(['success' => true], $data) : ['success' => false, 'message' => 'Invalid data stream matrix.'];
    }
}