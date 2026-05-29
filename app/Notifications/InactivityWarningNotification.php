<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InactivityWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $inactiveDays;

    public function __construct(int $inactiveDays)
    {
        $this->inactiveDays = $inactiveDays;
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
            ->subject('Markets are moving—don\'t miss out!')
            ->greeting("Hi {$notifiable->first_name},")
            ->line("It looks like you haven't logged into your Xavier account in over {$this->inactiveDays} days.")
            ->line('Global equity adjustments, digital asset movements, and premium technical analysis notes have dropped since your last session.')
            ->line('Log back in to review your open tracking parameters, active stock configurations, or advisory updates.')
            ->action('Launch Trading Dashboard', url('/login'))
            ->line('We are glad to have you on board.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => 'Account Dormancy Warning',
            'message' => "You haven't logged in for {$this->inactiveDays} days. Log back in to verify your positions.",
            'type'    => 'warning',
            'action'  => 'Go to Dashboard',
        ];
    }
}