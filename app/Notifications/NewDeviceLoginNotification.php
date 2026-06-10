<?php

namespace App\Notifications;

use App\Models\UserDevice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDeviceLoginNotification extends Notification
{
    public function __construct(private UserDevice $device)
    {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notificationPreferences?->email ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Security Alert: New Device Login')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('A login from a new device was detected on your account.')
            ->line("Device: {$this->device->device_name}")
            ->line("IP Address: {$this->device->ip_address}")
            ->line('If this was not you, please secure your account immediately by changing your password.')
            ->action('Review Security Sessions', url('/dashboard/settings/security'));
    }

    public function toArray($notifiable): array
    {
        $textMessage = 'A login from a new device was detected. If this was not you, please secure your account immediately.';

        return [
            'user_id' => $notifiable->id, 
            'message' => $textMessage,
            
            'type' => 'security',
            'title' => 'New Device Login',
            'message_text' => $textMessage, 
            'action' => 'Review Sessions',
            'action_url' => null,
            'icon' => '📱',
            'metadata' => [
                'device_name' => $this->device->device_name,
                'ip_address' => $this->device->ip_address,
                'created_at' => $this->device->created_at?->toDateTimeString(),
            ],
        ];
    }
}