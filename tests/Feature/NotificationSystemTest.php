<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\AdminBroadcastNotification;
use App\Notifications\NewDeviceLoginNotification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_endpoint_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        // Dispatch a real database notification
        $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Test billing'));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'unread_count',
                'notifications' => [
                    '*' => ['id', 'type', 'title', 'message', 'action', 'read', 'time'],
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_unread_count_reflects_unread_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\BillingAlertNotification(500, 'Fee due'));
        $user->notify(new \App\Notifications\InactivityWarningNotification(30));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $response->assertJsonPath('unread_count', 2);
    }

    public function test_user_can_read_and_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $user->notificationPreferences()->create([
            'email' => true,
            'sms' => true,
            'push' => false,
            'monthly_statements' => false,
            'newsletters' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/notifications/preferences');
        $response->assertStatus(200)
            ->assertJsonPath('data.email', true)
            ->assertJsonPath('data.push', false);

        $updateResponse = $this->actingAs($user)->putJson('/api/user/notifications/preferences', [
            'email' => false,
            'sms' => false,
            'push' => true,
            'monthly_statements' => true,
            'newsletters' => false,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.email', false)
            ->assertJsonPath('data.push', true);
    }

    public function test_notification_respects_email_preference(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email' => false,
            'sms' => true,
            'push' => true,
            'monthly_statements' => true,
            'newsletters' => false,
        ]);

        $notification = new \App\Notifications\BillingAlertNotification(1000, 'Pref test');

        $this->assertEquals(['database'], $notification->via($user));
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Test'));

        $notificationId = $user->notifications()->first()->id;

        $response = $this->actingAs($user)->postJson("/api/user/notifications/{$notificationId}/read");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotNull($user->notifications()->find($notificationId)->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Test 1'));
        $user->notify(new \App\Notifications\InactivityWarningNotification(30));

        $response = $this->actingAs($user)->postJson('/api/user/notifications/read-all');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_billing_notification_has_correct_type_and_action(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Insufficient balance'));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $notification = $response->json('notifications.0');
        $this->assertEquals('billing', $notification['type']);
        $this->assertEquals('Fund Wallet', $notification['action']);
    }

    public function test_account_suspended_notification_has_correct_type_and_action(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\AccountSuspendedNotification('Debt exceeded'));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $notification = $response->json('notifications.0');
        $this->assertEquals('account', $notification['type']);
        $this->assertEquals('Resolve Now', $notification['action']);
    }

    public function test_trial_ending_notification_has_correct_type_and_action(): void
    {
        $user = User::factory()->create();
        $user->notify(new \App\Notifications\TrialEndingNotification(2));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $notification = $response->json('notifications.0');
        $this->assertEquals('warning', $notification['type']);
        $this->assertEquals('Upgrade', $notification['action']);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/user/notifications');
        $response->assertStatus(401);
    }

    public function test_new_device_login_notification_has_correct_data(): void
    {
        $user = User::factory()->create();
        $device = UserDevice::factory()->for($user)->create([
            'device_name' => 'Test Device',
            'ip_address' => '192.168.1.1',
        ]);

        $user->notify(new NewDeviceLoginNotification($device));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $response->assertStatus(200);
        $notification = $response->json('notifications.0');

        $this->assertEquals('security', $notification['type']);
        $this->assertEquals('New Device Login', $notification['title']);
        $this->assertStringContainsString('A login from a new device was detected', $notification['message']);
        $this->assertEquals('Review Sessions', $notification['action']);
        $this->assertNull($notification['action_url']);
        $this->assertEquals('📱', $notification['icon']);
        $this->assertArrayHasKey('metadata', $notification);
        $this->assertEquals('Test Device', $notification['metadata']['device_name']);
        $this->assertEquals('192.168.1.1', $notification['metadata']['ip_address']);
    }

    public function test_admin_broadcast_notification_has_correct_data(): void
    {
        $user = User::factory()->create();
        $title = 'Important Announcement';
        $message = 'All systems are now fully operational.';

        $user->notify(new AdminBroadcastNotification($title, $message));

        $response = $this->actingAs($user)->getJson('/api/user/notifications');

        $response->assertStatus(200);
        $notification = $response->json('notifications.0');

        $this->assertEquals('broadcast', $notification['type']);
        $this->assertEquals($title, $notification['title']);
        $this->assertEquals($message, $notification['message']);
        $this->assertEquals('View Details', $notification['action']);
        $this->assertStringContainsString('/dashboard/notifications', $notification['action_url']);
        $this->assertEquals('📢', $notification['icon']);
    }
}
