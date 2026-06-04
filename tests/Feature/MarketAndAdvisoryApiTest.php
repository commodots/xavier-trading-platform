<?php

namespace Tests\Feature;

use App\Models\AdvisoryPost;
use App\Models\Symbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketAndAdvisoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_uk_market_data_through_api(): void
    {
        $user = User::factory()->create();

        Symbol::create([
            'symbol' => 'VOD',
            'name' => 'Vodafone Group',
            'type' => 'stock',
            'exchange' => 'LSE',
            'last_price' => 120.50,
            'change' => 1.25,
            'volume' => 920000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/markets?market=UK');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.symbol', 'VOD')
            ->assertJsonPath('data.0.market', 'UK')
            ->assertJsonPath('data.0.type', 'stock')
            ->assertJsonPath('data.0.trend', 'up');
    }

    public function test_crypto_market_data_is_normalized_and_includes_trend(): void
    {
        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['usd' => 52000],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/crypto');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.symbol', 'BTC')
            ->assertJsonPath('data.0.market', 'CRYPTO')
            ->assertJsonPath('data.0.type', 'crypto')
            ->assertJsonPath('data.0.trend', 'flat');
    }

    public function test_advisory_index_filters_by_market_type_and_tier_for_regular_user(): void
    {
        $user = User::factory()->create();

        AdvisoryPost::create([
            'title' => 'Free Crypto Alert',
            'content' => 'Buy the dip in BTC.',
            'market_type' => 'crypto',
            'risk_level' => 'medium',
            'tier' => 'free',
            'is_premium' => false,
        ]);

        AdvisoryPost::create([
            'title' => 'Premium Offshore Equity',
            'content' => 'Advanced hedge recommendations.',
            'market_type' => 'international',
            'risk_level' => 'high',
            'tier' => 'premium',
            'is_premium' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/advisories?market_type=crypto&tier=free');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Free Crypto Alert');
    }

    public function test_watchlist_endpoints_support_normalized_market_contract(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)->postJson('/api/watchlist', [
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
            'market' => 'US',
            'currency' => 'USD',
            'added_price' => 195.45,
        ]);

        $createResponse->assertStatus(200)
            ->assertJsonPath('data.symbol', 'AAPL')
            ->assertJsonPath('data.market', 'US')
            ->assertJsonPath('data.currency', 'USD');

        $watchlistId = $createResponse->json('data.id');

        $listResponse = $this->actingAs($user)->getJson('/api/watchlist');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.symbol', 'AAPL')
            ->assertJsonPath('data.0.market', 'US')
            ->assertJsonPath('data.0.currency', 'USD');

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/watchlist/{$watchlistId}");
        $deleteResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->actingAs($user)->getJson('/api/watchlist')->assertJsonCount(0, 'data');
    }

    public function test_watchlist_normalizes_market_categories(): void
    {
        $user = User::factory()->create();

        // Test normalizing NYSE to US
        $res1 = $this->actingAs($user)->postJson('/api/watchlist', [
            'symbol' => 'MSFT',
            'name' => 'Microsoft',
            'market' => 'NYSE',
            'currency' => 'USD',
            'added_price' => 380.50,
        ]);
        $res1->assertStatus(200)->assertJsonPath('data.market', 'US');

        // Test normalizing LSE to UK
        $res2 = $this->actingAs($user)->postJson('/api/watchlist', [
            'symbol' => 'VOD',
            'name' => 'Vodafone',
            'market' => 'LSE',
            'currency' => 'USD',
            'added_price' => 120.30,
        ]);
        $res2->assertStatus(200)->assertJsonPath('data.market', 'UK');

        // Test NGX passthrough
        $res3 = $this->actingAs($user)->postJson('/api/watchlist', [
            'symbol' => 'ZENITH',
            'name' => 'Zenith Bank',
            'market' => 'NGX',
            'currency' => 'NGN',
            'added_price' => 45.20,
        ]);
        $res3->assertStatus(200)->assertJsonPath('data.market', 'NGX');

        // Test CRYPTO passthrough
        $res4 = $this->actingAs($user)->postJson('/api/watchlist', [
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'market' => 'CRYPTO',
            'currency' => 'USD',
            'added_price' => 52000.00,
        ]);
        $res4->assertStatus(200)->assertJsonPath('data.market', 'CRYPTO');

        // Verify all are normalized in list response
        $listResponse = $this->actingAs($user)->getJson('/api/watchlist');
        $listResponse->assertStatus(200)->assertJsonCount(4, 'data');

        $markets = collect($listResponse->json('data'))->pluck('market')->sort()->values()->all();
        $this->assertEquals(['CRYPTO', 'NGX', 'UK', 'US'], $markets);
    }
}
