<?php

namespace Tests\Feature;

use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertDatabaseHas('new_transactions_table', [
            'user_id' => $user->id,
            'amount' => 5000,
            'charge' => 150,
        ]);
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

    public function test_user_can_convert_ngn_to_usd(): void
    {
        $user = User::factory()->create();
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
}
