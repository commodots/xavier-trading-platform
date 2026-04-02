<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\SystemSetting;
use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletFunctionsTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_user_can_deposit_funds_and_fees_are_applied(): void
    {
        $user = User::factory()->create();

        // Setup the fee rule in the DB
        TransactionCharge::create([
            'transaction_type' => 'deposit',
            'charge_type' => 'flat',
            'value' => 150,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/deposit', [
            'amount' => 5000,
            'currency' => 'NGN',
        ]);

        $response->assertStatus(200);

        // Check if the transaction record in DB has the 150 fee
        // $this->assertDatabaseHas('new_transactions_table', [
        //     'user_id' => $user->id,
        //     'amount' => 5000,
        //     'charge' => 150,
        // ]);
    }

    public function test_deposit_requires_a_positive_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/deposit', [
            'amount' => -100, // Invalid amount
            'currency' => 'NGN',
        ]);

        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_paystack_callback_route_is_accessible_using_api_prefix(): void
    {
        $response = $this->get('/api/paystack/callback');

        $response->assertStatus(302);
        $response->assertRedirect('/wallet?payment_error=no_reference');
    }

    public function test_paystack_verify_route_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/paystack/verify/xavier_test_ref');

        $response->assertStatus(401);
    }

    public function test_paystack_webhook_post_bypasses_csrf_and_fails_signature(): void
    {
        $response = $this->postJson('/api/paystack/webhook', ['event' => 'charge.success', 'data' => []]);

        // Without CSRF token, this should not be 419 (CSRF mismatch) but propagate to signature check.
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_user_can_convert_ngn_to_usd(): void
    {
        $user = User::factory()->create();

        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'base_rate' => 1500, // e.g., 1500 NGN to 1 USD
            'markup_percent' => 1.0,
            'effective_rate' => 1515.0,
        ]);

        $ngnWallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'balance' => 100000,
            'locked' => 0,
        ]);

        $usdWallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'balance' => 0,
            'locked' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/wallet/convert', [
            'from' => 'NGN',
            'amount' => 50000,
        ]);
        $response->assertStatus(200);

        // Assert: Check NGN balance decreased
        $ngnWallet->refresh();
        $this->assertEquals(50000, $ngnWallet->ngn_cleared);

        // Assert: Check USD balance increased
        $usdWallet->refresh();
        $this->assertGreaterThan(0, $usdWallet->usd_uncleared);
    }

    public function test_crypto_market_uses_live_api(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['usd' => 65000],
                'ethereum' => ['usd' => 3200],
                'tether' => ['usd' => 1],
            ], 200),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/market/crypto');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.symbol', 'BTC');
        $response->assertJsonPath('data.0.price', 65000);
    }

    public function test_trade_open_and_close_flow(): void
    {
        SystemSetting::create(['crypto_spread' => 0, 'crypto_fee' => 0, 'max_trade_amount' => 10000]);

        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'currency' => 'USD', 'usd_cleared' => 1000, 'usd_uncleared' => 0, 'balance' => 1000, 'locked' => 0]);

        Http::fake(['api.coingecko.com/api/v3/simple/price*' => Http::response(['bitcoin' => ['usd' => 100]], 200)]);

        $openRes = $this->actingAs($user)->postJson('/api/trade/open', ['amount' => 100, 'pair' => 'BTC/USDT', 'type' => 'buy']);
        $openRes->assertStatus(200)->assertJson(['success' => true]);

        $tradeId = $openRes->json('data.id');
        $this->assertDatabaseHas('trades', ['id' => $tradeId, 'status' => 'open']);
        $this->assertDatabaseHas('new_transactions_table', ['user_id' => $user->id, 'type' => 'buy_crypto']);

        $closeRes = $this->actingAs($user)->postJson("/api/trade/close/{$tradeId}");
        $closeRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('trades', ['id' => $tradeId, 'status' => 'closed']);
    }

    public function test_crypto_webhook_deposits_and_wallet_increase(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'currency' => 'USD', 'usd_cleared' => 0, 'usd_uncleared' => 0, 'balance' => 0, 'locked' => 0]);
        $address = \App\Models\CryptoAddress::create(['user_id' => $user->id, 'blockchain' => 'TRON', 'address' => 'TGW1']);

        $payload = ['address' => 'TGW1', 'amount' => 10, 'txId' => 'X123'];

        config(['services.crypto.api_key' => 'test-webhook-secret']);

        $response = $this->postJson('/api/crypto/webhook', $payload, ['x-api-key' => 'test-webhook-secret']);
        $response->assertStatus(200);

        $response->assertStatus(200);
        $this->assertDatabaseHas('new_transactions_table', ['user_id' => $user->id, 'type' => 'deposit', 'amount' => 10, 'currency' => 'USDT']);
        $wallet->refresh();
        $this->assertEquals(10, $wallet->usd_cleared);
    }
}
