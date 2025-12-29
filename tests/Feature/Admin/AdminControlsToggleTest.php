<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\TransactionType;

class AdminControlsToggleTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_non_admin_cannot_access_transaction_charges_index(): void
    {
        $user = User::factory()->create(['role' => 'user']); // A regular user

        $response = $this->actingAs($user)->getJson('/api/admin/transaction-charges');

        $response->assertStatus(403);
    }
    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/admin/transaction-charges');

        // It should be successful (200) or at least not 403
        $response->assertStatus(200);
    }
    public function test_users_cannot_withdraw_when_service_is_disabled(): void
    {
        $user = User::factory()->create();

        // 1. Setup: Disable the withdrawal service in the database
        TransactionType::create([
            'name'     => 'withdrawal',
            'category' => 'funding',
            'active'   => false
        ]);

        // 2. Action: Try to withdraw
        $response = $this->actingAs($user)->postJson('/api/withdraw', [
            'amount'   => 1000,
            'currency' => 'NGN'
        ]);

        // 3. Assertion: It should fail because the service is off
        $response->assertStatus(403);

        $response->assertJson([
            'success' => false,
            'message' => 'Withdrawals are temporarily disabled.'
        ]);
    }

    public function test_users_cannot_deposit_when_service_is_disabled(): void
{
    $user = User::factory()->create(); 

    TransactionType::create([
        'name' => 'deposit', 
        'category' => 'funding', 
        'active' => false]);

    $response = $this->actingAs($user)->postJson('/api/deposit', [
        'amount' => 5000,
        'currency' => 'NGN'
    ]);

    $response->assertStatus(403);

    $response->assertJson([
            'success' => false,
            'message' => 'Deposits are temporarily disabled.'
        ]);
}
}
