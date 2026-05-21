<?php

namespace Tests\Feature;

use App\Models\User;
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
}
