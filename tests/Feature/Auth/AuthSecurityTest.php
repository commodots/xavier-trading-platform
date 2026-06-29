<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Login
    // -------------------------------------------------------

    public function test_successful_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user']);
    }

    public function test_failed_login_logs_activity(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'  => $user->id,
            'activity' => 'Failed Login',
        ]);
    }

    public function test_successful_login_logs_activity(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'  => $user->id,
            'activity' => 'Login',
        ]);
    }

    public function test_login_records_device(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id]);
    }

    public function test_login_requires_2fa_when_enabled(): void
    {
        $user = User::factory()->create([
            'password'          => Hash::make('password123'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonMissing(['token']);
    }

    public function test_login_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'x@x.com', 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/login', ['email' => 'x@x.com', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    // -------------------------------------------------------
    // Session management
    // -------------------------------------------------------

    public function test_get_active_sessions_returns_token_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user/sessions');

        $response->assertStatus(200)->assertJsonStructure(['success', 'sessions']);
    }

    public function test_logout_other_devices_revokes_other_tokens(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-device');
        // Create an extra token simulating another device
        $user->createToken('other-device');

        $this->assertEquals(2, $user->tokens()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $currentToken->plainTextToken)
            ->postJson('/api/user/sessions/logout-others');

        $response->assertStatus(200)->assertJsonPath('success', true);
        // Only the current token should remain
        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }

    // -------------------------------------------------------
    // Password change
    // -------------------------------------------------------

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $response = $this->actingAs($user)->putJson('/api/user/security/password', [
            'current_password'      => 'OldPass123',
            'password'              => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('NewPass456!', $user->fresh()->password));
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $response = $this->actingAs($user)->putJson('/api/user/security/password', [
            'current_password'      => 'WrongPass',
            'password'              => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_password_change_logs_activity(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $this->actingAs($user)->putJson('/api/user/security/password', [
            'current_password'      => 'OldPass123',
            'password'              => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'  => $user->id,
            'activity' => 'Password Changed',
        ]);
    }

    public function test_password_change_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);
        $currentToken = $user->createToken('current-device');
        $user->createToken('other-device');

        $this->assertEquals(2, $user->tokens()->count());

        $this->withHeader('Authorization', 'Bearer ' . $currentToken->plainTextToken)
            ->putJson('/api/user/security/password', [
                'current_password'      => 'OldPass123',
                'password'              => 'NewPass456!',
                'password_confirmation' => 'NewPass456!',
            ]);

        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }
}
