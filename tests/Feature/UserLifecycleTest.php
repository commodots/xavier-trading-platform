<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Console\Commands\CheckUserInactivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_gets_403_on_api_request(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'suspended',
            'wallet_debt'         => 6000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/wallet/balances');

        $response->assertStatus(403);
        $response->assertJsonStructure(['error', 'debt']);
    }

    public function test_inactive_user_reactivated_on_api_request(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'inactive',
            'next_fee_due_at'     => now()->subDays(10),
        ]);

        $this->actingAs($user)->getJson('/api/wallet/balances');

        $user->refresh();
        $this->assertEquals('active', $user->subscription_status);
        $this->assertTrue($user->next_fee_due_at->isFuture());
    }

    public function test_last_active_at_updated_on_api_request(): void
    {
        $user = User::factory()->create(['last_active_at' => now()->subDays(5)]);

        $this->actingAs($user)->getJson('/api/wallet/balances');

        $user->refresh();
        $this->assertTrue($user->last_active_at->isToday());
    }

    public function test_inactivity_command_notifies_user_after_30_days(): void
    {
        $user = User::factory()->create([
            'last_active_at'      => now()->subDays(35),
            'subscription_status' => 'active',
        ]);

        $this->artisan('user:check-inactivity 30')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $user->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_inactivity_command_marks_user_inactive_after_60_days(): void
    {
        $user = User::factory()->create([
            'last_active_at'      => now()->subDays(65),
            'subscription_status' => 'active',
        ]);

        $this->artisan('user:check-inactivity 30')->assertSuccessful();

        $this->assertEquals('inactive', $user->fresh()->subscription_status);
    }

    public function test_inactivity_command_skips_admin_users(): void
    {
        $admin = User::factory()->create([
            'last_active_at'      => now()->subDays(65),
            'subscription_status' => 'active',
            'role'                => 'admin',
        ]);

        $this->artisan('user:check-inactivity 30')->assertSuccessful();

        $this->assertEquals('active', $admin->fresh()->subscription_status);
    }
}
