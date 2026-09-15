<?php

namespace Tests\Feature\CSL;

use App\Services\CSL\CslClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CslAuthenticationTest extends TestCase
{
    public function test_access_token_is_cached_after_oauth_response(): void
    {
        config()->set('services.csl.client_id', 'client');
        config()->set('services.csl.client_secret', 'secret');
        config()->set('services.csl.oauth_url', 'https://csl.test/oauth/token');
        Cache::forget('csl.oauth.access_token');
        Http::fake(['https://csl.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600])]);

        $client = app(CslClient::class);

        $this->assertSame('token', $client->getAccessToken());
        $this->assertSame('token', $client->getAccessToken());
        Http::assertSentCount(1);
    }
}
