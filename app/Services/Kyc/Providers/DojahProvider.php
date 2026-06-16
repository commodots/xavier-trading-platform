<?php

namespace App\Services\Kyc\Providers;

use App\Services\Kyc\Contracts\KycProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DojahProvider implements KycProviderInterface
{
    /**
     * Build HTTP client with Dojah headers.
     */
    protected function client()
    {
        return Http::withHeaders([
            'AppId' => config('services.dojah.app_id'),
            'Authorization' => config('services.dojah.secret_key'),
            'Accept' => 'application/json',
        ])->timeout(15);
    }

    /**
     * Build full URL from path.
     */
    protected function url(string $path): string
    {
        return rtrim(config('services.dojah.base_url', 'https://api.dojah.io'), '/') . $path;
    }

    /**
     * Verify BVN via Dojah.
     */
    public function verifyBvn(string $bvn): array
    {
        try {
            $response = $this->client()
                ->get($this->url('/api/v1/kyc/bvn'), [
                    'bvn' => $bvn,
                ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('Dojah BVN verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'BVN verification gateway error.',
            ];
        }
    }

    /**
     * Verify NIN via Dojah.
     */
    public function verifyNin(string $nin): array
    {
        try {
            $response = $this->client()
                ->get($this->url('/api/v1/kyc/nin'), [
                    'nin' => $nin,
                ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('Dojah NIN verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'NIN verification gateway error.',
            ];
        }
    }

    /**
     * Verify face / liveness via Dojah.
     * Connects to the existing verify-liveness endpoint.
     */
    public function verifyFace(array $data): array
    {
        try {
            $base64Image = $data['image'] ?? $data['base64_image'] ?? null;

            if (!$base64Image) {
                return [
                    'success' => false,
                    'message' => 'Image data is required for face verification.',
                ];
            }

            $response = $this->client()
                ->timeout(30)
                ->post($this->url('/api/v1/kyc/selfie'), [
                    'image' => $base64Image,
                ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('Dojah face verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Face verification gateway error.',
            ];
        }
    }

    /**
     * Parse Dojah API response.
     */
    protected function parseResponse($response): array
    {
        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('error')
                    ?? $response->json('message')
                    ?? 'Dojah API response error.',
            ];
        }

        $data = $response->json();

        if (is_array($data)) {
            return array_merge(['success' => true], $data);
        }

        return [
            'success' => false,
            'message' => 'Invalid response from Dojah.',
        ];
    }
}