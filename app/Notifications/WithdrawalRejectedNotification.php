<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRejectedNotification extends Notification
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
            ->error()
            ->subject('Withdrawal Request Rejected')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} has been rejected.")
            ->line("Reason: Please contact support or check your account status for more details.")
            ->action('View Details', url('/dashboard/security/withdrawals'))
            ->line('If you have questions regarding this rejection, please reach out to our compliance team.');
    }

    public function toArray($notifiable): array
    {
        $textMessage = "Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} has been rejected.";

        return [
            'user_id' => $notifiable->id,
            'message' => $textMessage,
            
            'type' => 'withdrawal',
            'title' => 'Withdrawal Rejected',
            'action' => 'View Withdrawal',
            'action_url' => null,
            'icon' => '⚠️',
            'metadata' => [
                'withdrawal_id' => $this->withdrawal->id,
                'currency' => $this->withdrawal->currency,
                'amount' => $this->withdrawal->amount,
            ],
        ];
    }
}