<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\TransactionCharge;
use App\Models\NewTransaction;
use App\Models\PlatformEarning;

class TransactionChargeTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_it_allows_mass_assignment_on_transaction_charges(): void
    {
        $data = [
            'transaction_type' => 'withdrawal',
            'charge_type' => 'percentage',
            'value' => 5.0,
            'active' => true
        ];

      
        $charge = TransactionCharge::create($data);

        $this->assertDatabaseHas('transaction_charges', ['transaction_type' => 'withdrawal']);
        $this->assertEquals(5.0, $charge->value);
    }
    public function test_it_calculates_percentage_fees_correctly(): void
    {
       // 1. Create a 5% fee rule
        TransactionCharge::create([
            'transaction_type' => 'withdrawal',
            'charge_type' => 'percentage',
            'value' => 5.0,
            'active' => true
        ]);

        // 2. Calculate for 10,000 NGN
        $fee = TransactionCharge::calculate('withdrawal', 10000);

        $this->assertEquals(500, $fee);
    }
    public function test_it_logs_platform_earnings_when_calculating_with_a_transaction(): void
    {
        $user = User::factory()->create();
        
        // 1. Setup a flat 200 NGN fee
        TransactionCharge::create([
            'transaction_type' => 'deposit',
            'charge_type' => 'flat',
            'value' => 200,
            'active' => true
        ]);

        // 2. Create a dummy transaction
        $transaction = NewTransaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'completed'
        ]);

        // 3. Run calculation linked to this transaction
        TransactionCharge::calculate('deposit', 5000, $transaction);

        // 4. Assertions
        $this->assertEquals(200, $transaction->fresh()->charge);
        $this->assertDatabaseHas('platform_earnings', [
            'transaction_id' => $transaction->id,
            'amount' => 200
        ]);
    }
}
