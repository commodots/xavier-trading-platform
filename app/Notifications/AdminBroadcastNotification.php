<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AdminBroadcastNotification extends Notification implements ShouldQueue
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
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => 'info'
        ];
    }
}