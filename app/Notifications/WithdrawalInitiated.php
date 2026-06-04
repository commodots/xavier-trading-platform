<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Notifications\Notification;

class WithdrawalInitiated extends Notification
{
    public function __construct(private WithdrawalRequest $withdrawal)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'withdrawal',
            'title' => 'Withdrawal Request Initiated',
            'message' => "Your withdrawal request of {$this->withdrawal->currency} {$this->withdrawal->amount} is pending approval.",
            'action' => 'View Details',
            'action_url' => route('withdrawals.show', $this->withdrawal),
            'icon' => '💸',
            'metadata' => [
                'withdrawal_id' => $this->withdrawal->id,
                'amount' => $this->withdrawal->amount,
                'currency' => $this->withdrawal->currency,
            ],
        ];
    }
}
