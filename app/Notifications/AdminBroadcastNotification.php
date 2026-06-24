<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public bool $sendEmail = false,
        public bool $sendMessage = false,
        public string $type = 'info'
    ) {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($this->sendEmail && ($notifiable->notificationPreferences?->email ?? true)) {
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
            'message' => $this->message,
            'type' => $this->type,
            'action' => 'View Details',
            'action_url' => url('/notifications'),
            'icon' => '📢',
        ];
    }
}