<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoOrder;
use App\Models\Demo\DemoPortfolio;
use App\Models\Demo\DemoTransaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user and their associated wallets
        $this->user = User::factory()->create([
            'trading_mode' => 'live', // Default to live mode
        ]);

        // Create live wallets
        Wallet::factory()->for($this->user)->create(['currency' => 'NGN']);
        Wallet::factory()->for($this->user)->create(['currency' => 'USD']);

        // Create demo wallets with initial funding
        DemoWallet::factory()->for($this->user)->create([
            'currency' => 'NGN', 
            'balance' => 1000000,
            'ngn_cleared' => 1000000,
        ]);
        DemoWallet::factory()->for($this->user)->create([
            'currency' => 'USD', 
            'balance' => 10000,
            'usd_cleared' => 10000,
        ]);
    }

    public function test_user_can_switch_to_demo_mode(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/switch-mode', ['mode' => 'demo']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Switched to DEMO mode',
                'trading_mode' => 'demo',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'trading_mode' => 'demo',
        ]);
    }

    public function test_user_can_switch_back_to_live_mode(): void
    {
        $this->user->update(['trading_mode' => 'demo']);
        $this->actingAs($this->user);

        $response = $this->postJson('/api/switch-mode', ['mode' => 'live']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Switched to LIVE mode',
                'trading_mode' => 'live',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'trading_mode' => 'live',
        ]);
    }

    public function test_user_in_demo_mode_can_fund_wallet_instantly_via_deposit_endpoint(): void
    {
        $this->user->update(['trading_mode' => 'demo']);
        $this->actingAs($this->user);

        $initialBalance = DemoWallet::where('user_id', $this->user->id)->where('currency', 'NGN')->value('balance');
        $fundingAmount = 50000;

        // The frontend calls /paystack/initiate for deposits
        // Note: Ensure PaystackController handles the 'demo' trading_mode check!
        $response = $this->postJson('/api/paystack/initiate', ['amount' => $fundingAmount]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_demo' => true,
                'message' => 'Demo account instantly funded!',
            ]);

        $newBalance = DemoWallet::where('user_id', $this->user->id)->where('currency', 'NGN')->value('balance');
        $this->assertEquals($initialBalance + $fundingAmount, $newBalance);

        $this->assertDatabaseHas('demo_transactions', [
            'user_id' => $this->user->id,
            'type' => 'deposit',
            'amount' => $fundingAmount,
            'status' => 'completed',
            'currency' => 'NGN',
        ]);
    }

    public function test_user_in_demo_mode_can_place_buy_trade(): void
    {
        $this->user->update(['trading_mode' => 'demo']);
        $this->actingAs($this->user);

        $wallet = DemoWallet::where('user_id', $this->user->id)->where('currency', 'NGN')->first();
        $initialBalance = $wallet->balance;
        $tradeAmount = 10000;
        $marketPrice = 50.00;
        $quantity = $tradeAmount / $marketPrice;

        $payload = [
            'symbol' => 'MTNN',
            'market' => 'NGX',
            'side' => 'buy',
            'amount' => $tradeAmount,
            'market_price' => $marketPrice,
        ];

        $response = $this->postJson('/api/demo/trade', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'filled',
                'symbol' => 'MTNN',
                'side' => 'buy',
                'amount' => $tradeAmount,
            ]);

        $this->assertDatabaseHas('demo_orders', ['user_id' => $this->user->id, 'symbol' => 'MTNN', 'side' => 'buy']);
        $wallet->refresh();
        $this->assertEquals($initialBalance - $tradeAmount, $wallet->balance);
        $this->assertDatabaseHas('demo_portfolios', ['user_id' => $this->user->id, 'symbol' => 'MTNN', 'cleared_quantity' => $quantity]);
    }

    public function test_portfolio_endpoint_returns_demo_data_in_demo_mode(): void
    {
        DemoPortfolio::factory()->create(['user_id' => $this->user->id, 'symbol' => 'DEMOSTOCK']);
        $this->user->update(['trading_mode' => 'demo']);
        $this->actingAs($this->user);

        $response = $this->getJson('/api/portfolio');

        $response->assertStatus(200)->assertJson(['success' => true, 'mode' => 'demo'])->assertJsonFragment(['symbol' => 'DEMOSTOCK']);
    }

    public function test_portfolio_endpoint_returns_live_data_in_live_mode(): void
    {
        DemoPortfolio::factory()->create(['user_id' => $this->user->id, 'symbol' => 'DEMOSTOCK']);
        $this->actingAs($this->user); // User is in 'live' mode by default

        $response = $this->getJson('/api/portfolio');

        $response->assertStatus(200)->assertJson(['success' => true, 'mode' => 'live'])->assertJsonMissing(['symbol' => 'DEMOSTOCK']);
    }

    public function test_user_can_reset_demo_account(): void
    {
        $this->actingAs($this->user);

        DemoOrder::factory()->create(['user_id' => $this->user->id]);
        DemoPortfolio::factory()->create(['user_id' => $this->user->id, 'symbol' => 'TEST']);
        $wallet = DemoWallet::where('user_id', $this->user->id)->where('currency', 'NGN')->first();
        $wallet->update(['balance' => 50000]);

        $response = $this->postJson('/api/demo/reset');

        $response->assertStatus(200)->assertJson(['message' => 'Demo reset successful']);
        $this->assertDatabaseMissing('demo_orders', ['user_id' => $this->user->id]);
        $this->assertDatabaseMissing('demo_portfolios', ['user_id' => $this->user->id, 'symbol' => 'TEST']);
        $wallet->refresh();
        $this->assertEquals(1000000, $wallet->balance);
    }
}
