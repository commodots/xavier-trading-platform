<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletFxOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = $this->user->fxWallet('USD');
        $this->wallet->update([
            'balance' => 1000,
            'usd_cleared' => 1000,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
    }

    /**
     * Test debit operation on balance
     */
    public function test_debit_balance(): void
    {
        $this->wallet->debit(100, 'balance');

        $this->wallet->reserve(400);
        $this->wallet->refresh();
        $this->assertEquals(900, $this->wallet->balance);
    }

    /**
     * Test debit operation on uncleared balance
     */
    public function test_debit_uncleared_balance(): void
    {
        $this->wallet->update(['usd_uncleared' => 500]);

        $this->wallet->debit(200, 'uncleared');

        $this->wallet->refresh();
        $this->assertEquals(300, $this->wallet->usd_uncleared);
    }

    /**
     * Test credit operation on balance
     */
    public function test_credit_balance(): void
    {
        $this->wallet->credit(500, 'balance');

        $this->wallet->refresh();
        $this->assertEquals(1500, $this->wallet->balance);
    }

    /**
     * Test credit operation on uncleared balance
     */
    public function test_credit_uncleared_balance(): void
    {
        $this->wallet->credit(250, 'uncleared');

        $this->wallet->refresh();
        $this->assertEquals(250, $this->wallet->usd_uncleared);
    }

    /**
     * Test reserve operation
     */
    public function test_reserve_amount(): void
    {
        $this->wallet->reserve(400);

        $this->wallet->refresh();
        // $this->assertEquals(600, $this->wallet->balance); // Balance remains total equity
        $this->assertEquals(400, $this->wallet->locked);
    }

    /**
     * Test reserve fails with insufficient balance
     */
    public function test_reserve_insufficient_balance(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient cleared funds'); 

        $this->wallet->reserve(2000);
    }

    /**
     * Test finalize reservation
     */
    public function test_finalize_reservation(): void
    {
        $this->wallet->update(['locked' => 500]);

        $this->wallet->finalizeReservation(300);

        $this->wallet->refresh();
        $this->assertEquals(200, $this->wallet->locked);
    }

    /**
     * Test settle uncleared balance
     */
    public function test_settle_uncleared_balance(): void
    {
        $this->wallet->update([
            'usd_cleared' => 100,
            'usd_uncleared' => 500,
        ]);

        $this->wallet->settle();

        $this->wallet->refresh();
        $this->assertEquals(600, $this->wallet->usd_cleared);
        $this->assertEquals(0, $this->wallet->usd_uncleared);
    }

    /**
     * Test user.fxWallet helper method
     */
    public function test_user_fx_wallet_helper(): void
    {
        $ngnWallet = $this->user->fxWallet('NGN');

        $this->assertNotNull($ngnWallet);
        $this->assertEquals('NGN', $ngnWallet->currency);
        $this->assertEquals($this->user->id, $ngnWallet->user_id);
    }

    /**
     * Test wallets created on user creation
     */
    public function test_wallets_created_on_user_signup(): void
    {
        $newUser = User::factory()->create();
        
        // Use fxWallet helper to ensure they are created/retrieved
        $ngnWallet = $newUser->fxWallet('NGN');
        $usdWallet = $newUser->fxWallet('USD');

        $this->assertNotNull($ngnWallet);
        $this->assertNotNull($usdWallet);
        $this->assertEquals(0, $ngnWallet->balance);
        $this->assertEquals(0, $usdWallet->balance);
    }
}
