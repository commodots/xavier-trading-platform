<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalInitiated extends Notification
{
    public function __construct(private WithdrawalRequest $withdrawal) {}

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
            ->subject('Withdrawal Request Received')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} has been received.")
            ->line('Status: Pending Approval')
            ->action('View Withdrawal Details', url("/dashboard/security/withdrawals/{$this->withdrawal->id}"))
            ->line('If you did not authorize this request, please contact support immediately.');
    }

    public function toArray($notifiable): array
    {
        $textMessage = "Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} is pending approval.";

        return [
            'user_id' => $notifiable->id,
            'message' => $textMessage,

            'type' => 'withdrawal',
            'title' => 'Withdrawal Request Initiated',
            'action' => 'View Details',
            'action_url' => "/dashboard/security/withdrawals/{$this->withdrawal->id}",
            'icon' => '💸',
            'metadata' => [
                'withdrawal_id' => $this->withdrawal->id,
                'amount' => $this->withdrawal->amount,
                'currency' => $this->withdrawal->currency,
            ],
        ];
    }
}
