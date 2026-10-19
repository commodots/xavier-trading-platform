<?php

namespace Tests\Feature;

use App\Models\NewTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_retrieve_another_users_transaction_by_id(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $transaction = NewTransaction::create([
            'user_id' => $owner->id,
            'type' => 'deposit',
            'amount' => 100,
            'currency' => 'NGN',
            'charge' => 0,
            'net_amount' => 100,
            'status' => 'completed',
        ]);

        $this->actingAs($requester)
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertNotFound()
            ->assertJson(['message' => 'Transaction not found']);
    }

    public function test_user_can_retrieve_their_own_transaction(): void
    {
        $owner = User::factory()->create();
        $transaction = NewTransaction::create([
            'user_id' => $owner->id,
            'type' => 'deposit',
            'amount' => 100,
            'currency' => 'NGN',
            'charge' => 0,
            'net_amount' => 100,
            'status' => 'completed',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $transaction->id);
    }
}