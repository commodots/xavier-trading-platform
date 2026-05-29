<?php

namespace Tests\Feature\Admin;

use App\Models\AdminNotificationLog;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_send_notifications(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $recipient = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/admin/notifications/send', [
            'user_ids' => [$recipient->id],
            'title' => 'Test Alert',
            'message' => 'This is a test notification.',
            'send_email' => false,
            'send_message' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_send_in_app_notifications_to_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recipients = User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/notifications/send', [
            'user_ids' => $recipients->pluck('id')->toArray(),
            'title' => 'Platform Update',
            'message' => 'A new feature is live.',
            'send_email' => false,
            'send_message' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification dispatch initiated successfully.');

        $this->assertDatabaseCount('notifications', 3);
        $this->assertDatabaseHas('admin_notification_logs', [
            'title' => 'Platform Update',
            'recipient_count' => 3,
            'sent_email' => false,
            'sent_message' => true,
        ]);

        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $recipient->id,
                'type' => 'App\\Notifications\\AdminBroadcastNotification',
            ]);
        }
    }

    public function test_admin_can_send_email_and_message_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recipient = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/notifications/send', [
            'user_ids' => [$recipient->id],
            'title' => 'Email Broadcast',
            'message' => 'Please check your inbox and dashboard.',
            'send_email' => true,
            'send_message' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('admin_notification_logs', [
            'title' => 'Email Broadcast',
            'recipient_count' => 1,
            'sent_email' => true,
            'sent_message' => true,
        ]);
    }
}
