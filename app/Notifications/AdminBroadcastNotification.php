<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBroadcastNotification extends Notification
{
    use Queueable;

    public string $title;

    public string $message;

    public bool $sendEmail;

    public bool $sendMessage;

    public function __construct(string $title, string $message, bool $sendEmail, bool $sendMessage)
    {
        $this->title = $title;
        $this->message = $message;
        $this->sendEmail = $sendEmail;
        $this->sendMessage = $sendMessage;
    }

    public function via($notifiable): array
    {
        $channels = [];

        // Only send to database if sendMessage is requested
        if ($this->sendMessage) {
            $channels[] = 'database';
        }

        if ($this->sendEmail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'info',
            'action' => '/dashboard', // Default action for general admin broadcasts
        ];
    }
}
