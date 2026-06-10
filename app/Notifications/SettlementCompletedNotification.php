<?php

namespace App\Notifications;

use App\Models\Trade;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SettlementCompletedNotification extends Notification
{
    public function __construct(private Trade $trade)
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
            ->subject('Trade Settlement Completed')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("The settlement for your trade on {$this->trade->pair} has been completed successfully.")
            ->line("Amount: {$this->trade->currency} " . number_format($this->trade->total_amount, 2))
            ->action('View Portfolio', url('/dashboard/portfolio'))
            ->line('Thank you for trading with Xavier.');
    }

    public function toArray($notifiable): array
    {
        $textMessage = 'Your trade settlement has completed successfully.';

        return [
            'user_id' => $notifiable->id,
            'message' => $textMessage,
            
            'type' => 'settlement',
            'title' => 'Settlement Completed',
            'action' => 'View Trade',
            'action_url' => null,
            'icon' => '✅',
            'metadata' => [
                'trade_id' => $this->trade->id,
                'order_id' => $this->trade->order_id,
                'amount' => $this->trade->total_amount,
                'currency' => $this->trade->currency,
            ],
        ];
    }
}