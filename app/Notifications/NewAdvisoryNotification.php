<?php

namespace App\Notifications;


use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAdvisoryNotification extends Notification
{

    public $advisoryPost;

    /**
     * Create a new notification instance.
     */
    public function __construct($advisoryPost)
    {
        $this->advisoryPost = $advisoryPost;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // 1. ALWAYS send the in-app database notification
        $channels = ['database'];

        // 2. Safely check if they have preferences set. Default to true if they don't.
        $wantsEmail = $notifiable->notificationPreference ? $notifiable->notificationPreference->email : true;

        if ($wantsEmail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Fallback to 'name' or 'User' if first_name doesn't exist on your User model
        $userName = $notifiable->first_name ?? $notifiable->name ?? 'Valued User';

        return (new MailMessage)
            ->subject('New Advisory Update: ' . $this->advisoryPost->title)
            ->greeting('Hello ' . $userName . ',')
            ->line('A new advisory post has been published.')
            ->line('Title: ' . $this->advisoryPost->title)
            ->action('View Advisory', url('/advisory/' . $this->advisoryPost->id))
            ->line('Thank you for using our platform!');
    }

    /**
     * Get the array representation of the notification for the Database.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Advisory Posted',
            'message' => 'Check out the latest advisory: ' . $this->advisoryPost->title,
            'post_id' => $this->advisoryPost->id,
            'type' => 'advisory'
        ];
    }
}