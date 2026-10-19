<?php

namespace Tests\Feature;

use App\Models\NewTransaction;
use App\Models\PlatformEarning;
use App\Models\User;
use Database\Seeders\NewTransactionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewTransactionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_transaction_seeder_is_idempotent_and_only_targets_demo_users(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UserSeeder::class);
        $ordinaryUser = User::factory()->create(['email' => 'ordinary@example.com']);

        $this->seed(NewTransactionSeeder::class);

        $transactionCountAfterFirstRun = NewTransaction::query()->count();
        $earningCountAfterFirstRun = PlatformEarning::query()->count();

        $this->seed(NewTransactionSeeder::class);

        $this->assertSame($transactionCountAfterFirstRun, NewTransaction::query()->count());
        $this->assertSame($earningCountAfterFirstRun, PlatformEarning::query()->count());
        $this->assertDatabaseMissing('new_transactions_table', [
            'user_id' => $ordinaryUser->id,
        ]);
        $this->assertSame(20, NewTransaction::query()->count());
    }
}