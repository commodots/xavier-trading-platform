<?php

namespace App\Services\CSL;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CslClient
{
    /**
     * CSL OAuth token cache key.
     */
    protected string $tokenCacheKey = 'csl.oauth.access_token';

    /**
     * Get the CSL OAuth access token.
     */
    public function getAccessToken(): string
    {
        $cachedToken = Cache::get($this->tokenCacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $clientId = config('services.csl.client_id');
        $clientSecret = config('services.csl.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new RuntimeException(
                'CSL client credentials are not configured.'
            );
        }

        $tokenUrl = config('services.csl.oauth_url');

        /** 
         * The CSL OAuth endpoint is:
         *
         * /oauth/token
         *
         * We remove the /cor/sxt portion from the API URL
         * and append /oauth/token.
         */

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(config('services.csl.timeout', 30))
            ->connectTimeout(
                config('services.csl.connect_timeout', 10)
            )
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Unable to authenticate with CSL. HTTP status: '
                . $response->status()
            );
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new RuntimeException(
                'CSL authentication succeeded but no access token was returned.'
            );
        }

        /** 
         * CSL token responses normally include expires_in.
         *
         * Cache slightly shorter than the actual expiry so we don't
         * accidentally use an expired token.
         */
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        $cacheSeconds = max(
            60,
            $expiresIn - 60
        );

        Cache::put(
            $this->tokenCacheKey,
            $data['access_token'],
            now()->addSeconds($cacheSeconds)
        );

        return $data['access_token'];
    }

    /**
     * Build an authenticated CSL request.
     */
    public function request(
        string $baseUrl,
        string $method,
        string $endpoint,
        array $data = []
    ): Response {

        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $request = $this->http()
            ->withToken($this->getAccessToken());

        return match (strtoupper($method)) {

            'GET' => $request->get($url, $data),

            'POST' => $request->post($url, $data),

            'PUT' => $request->put($url, $data),

            'PATCH' => $request->patch($url, $data),

            'DELETE' => $request->delete($url, $data),

            default => throw new RuntimeException(
                "Unsupported HTTP method: {$method}"
            ),
        };
    }

    /**
     * CSL Stock Broking API request.
     */
    public function st(
        string $method,
        string $endpoint,
        array $data = []
    ): Response {
        return $this->request(
            config('services.csl.st_base_url'),
            $method,
            $endpoint,
            $data
        );
    }

    /**
     * CSL Trade X / real-time trading API request.
     */
    public function xt(
        string $method,
        string $endpoint,
        array $data = []
    ): Response {
        return $this->request(
            config('services.csl.xt_base_url'),
            $method,
            $endpoint,
            $data
        );
    }

    /**
     * Base HTTP client configuration.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(config('services.csl.timeout', 30))
            ->connectTimeout(
                config('services.csl.connect_timeout', 10)
            )
            ->retry(
                2,
                500,
                throw: false
            );
    }

    /**
     * Forget the cached token.
     */
    public function clearToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }
}