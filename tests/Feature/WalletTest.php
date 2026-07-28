<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_their_wallet()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        
        $response = $this->get('/wallet');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /** @test */
    public function user_can_deposit_funds()
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        
        $response = $this->postJson('/wallet/deposit', [
            'amount' => 10000,
            'currency' => 'NGN',
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /** @test */
    public function user_cannot_deposit_below_minimum()
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        
        $response = $this->postJson('/wallet/deposit', [
            'amount' => 50,
            'currency' => 'NGN',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }
}