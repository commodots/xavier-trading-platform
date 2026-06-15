<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AdminBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $message)
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
            ->subject($this->title)
            ->line($this->message)
            ->action('View Details', url('/dashboard/notifications'));
    }

    public function toArray($notifiable): array
    {
        return [
            'category' => 'broadcast',
            'title' => $this->title,
            'message_text' => $this->message,
            'action' => 'View Details',
            'action_url' => url('/dashboard/notifications'),
            'icon' => '📢',
        ];
    }
}