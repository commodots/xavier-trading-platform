<?php

namespace App\Services\CSL;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CslClient
{
    protected string $tokenCacheKey = 'csl.oauth.access_token';

    /**
     * Get CSL OAuth access token.
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey);

        if ($cached) {
            return $cached;
        }

        $clientId = config('services.csl.client_id');
        $clientSecret = config('services.csl.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException(
                'CSL client credentials are not configured.'
            );
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(config('services.csl.timeout', 30))
            ->connectTimeout(
                config('services.csl.connect_timeout', 10)
            )
            ->post(
                config('services.csl.oauth_url'),
                [
                    'grant_type' => 'client_credentials',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'CSL authentication failed. HTTP '
                .$response->status()
                .': '
                .$response->body()
            );
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new RuntimeException(
                'CSL authentication response did not contain access_token.'
            );
        }

        $expiresIn = (int) (
            $data['expires_in'] ?? 3600
        );

        Cache::put(
            $this->tokenCacheKey,
            $data['access_token'],
            now()->addSeconds(
                max(60, $expiresIn - 60)
            )
        );

        return $data['access_token'];
    }

    /**
     * Execute an authenticated CSL request.
     */
    public function request(
        string $baseUrl,
        string $method,
        string $endpoint,
        array $data = []
    ): Response {

        $url = rtrim($baseUrl, '/')
            .'/'
            .ltrim($endpoint, '/');

        $request = $this->http()
            ->withToken($this->getAccessToken());

        return match (strtoupper($method)) {
            'GET' => $request->get($url, $data),

            'POST' => $request->post($url, $data),

            'PUT' => $request->put($url, $data),

            'PATCH' => $request->patch($url, $data),

            'DELETE' => $request->delete($url, $data),

            default => throw new RuntimeException(
                "Unsupported CSL HTTP method: {$method}"
            ),
        };
    }

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

    public function orderRequest(
        string $method,
        string $endpoint,
        array $data = []
    ): Response {
        $url = rtrim(config('services.csl.xt_base_url'), '/')
            .'/'.ltrim($endpoint, '/');

        $request = $this->httpWithoutRetry()
            ->withToken($this->getAccessToken());

        return match (strtoupper($method)) {
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new RuntimeException(
                "Unsupported CSL order HTTP method: {$method}"
            ),
        };
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(config('services.csl.timeout', 30))
            ->connectTimeout(
                config('services.csl.connect_timeout', 10)
            )
            ->retry(2, 500, throw: false);
    }

    protected function httpWithoutRetry(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(config('services.csl.timeout', 30))
            ->connectTimeout(config('services.csl.connect_timeout', 10));
    }

    /**
     * Convert a CSL response to an array and handle HTTP errors.
     */
    public function json(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'CSL API request failed. HTTP '
                .$response->status()
                .': '
                .$response->body()
            );
        }

        $json = $response->json();

        return is_array($json)
            ? $json
            : [];
    }

    public function clearToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }
}
