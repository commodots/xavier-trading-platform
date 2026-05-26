<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_user_device_record(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id]);
    }

    public function test_same_device_login_updates_last_active_at(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        // First login
        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $firstLogin = UserDevice::where('user_id', $user->id)->first()->last_active_at;

        // Travel forward in time
        $this->travel(5)->minutes();

        // Second login from same device
        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $secondLogin = UserDevice::where('user_id', $user->id)->first()->last_active_at;

        // Should be updated, not duplicated
        $this->assertEquals(1, UserDevice::where('user_id', $user->id)->count());
        $this->assertTrue($secondLogin->greaterThan($firstLogin));
    }

    public function test_get_active_sessions_lists_current_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user/sessions');

        $response->assertStatus(200);
        $sessions = $response->json('sessions');
        $this->assertNotEmpty($sessions);

        $current = collect($sessions)->firstWhere('is_current', true);
        $this->assertNotNull($current);
    }

    public function test_logout_other_devices_only_keeps_current_session(): void
    {
        $user = User::factory()->create();

        // Create all tokens upfront - test-device will be the "current" token
        $currentToken = $user->createToken('test-device');
        $user->createToken('device-2');
        $user->createToken('device-3');

        // 3 tokens total
        $this->assertEquals(3, $user->tokens()->count());

        // Make request using the current token
        $response = $this->withHeader('Authorization', 'Bearer ' . $currentToken->plainTextToken)
            ->postJson('/api/user/sessions/logout-others');

        $response->assertStatus(200);
        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }
}
