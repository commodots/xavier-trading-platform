<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeChargedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected float $amount,
        protected string $nextDueAt
    ) {}

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
            ->subject('Platform Fee Charged Successfully')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your quarterly platform access fee of ₦" . number_format($this->amount, 2) . " has been successfully charged from your wallet.")
            ->line("Your next billing date is: {$this->nextDueAt}.")
            ->line('Thank you for using Xavier.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => 'Platform Fee Charged',
            'message' => "₦" . number_format($this->amount, 2) . " platform access fee charged. Next due: {$this->nextDueAt}.",
            'type'    => 'billing',
        ];
    }
}
