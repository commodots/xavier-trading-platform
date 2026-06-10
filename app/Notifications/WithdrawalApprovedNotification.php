<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalApprovedNotification extends Notification
{
    public function __construct(private WithdrawalRequest $withdrawal)
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
            ->subject('Withdrawal Approved')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Great news! Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} has been approved.")
            ->line("Funds should arrive in your account shortly.")
            ->action('View Transaction', url('/dashboard/wallet'))
            ->line('Thank you for using Xavier.');
    }

    public function toArray($notifiable): array
    {
        $textMessage = "Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} has been approved.";

        return [
            'user_id' => $notifiable->id,
            'message' => $textMessage,
            
            'type' => 'withdrawal',
            'title' => 'Withdrawal Approved',
            'action' => 'View Withdrawal',
            'action_url' => null,
            'icon' => '✅',
            'metadata' => [
                'withdrawal_id' => $this->withdrawal->id,
                'currency' => $this->withdrawal->currency,
                'amount' => $this->withdrawal->amount,
            ],
        ];
    }
}