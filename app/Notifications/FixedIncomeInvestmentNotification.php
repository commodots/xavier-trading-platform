<?php

namespace App\Notifications;

use App\Models\FixedIncomeInvestment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FixedIncomeInvestmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public FixedIncomeInvestment $investment,
        public string $event
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'fixed_income',
            'event' => $this->event,

            'investment_id' =>
                $this->investment->id,

            'reference' =>
                $this->investment->reference,

            'product' =>
                $this->investment->product?->name,

            'amount' =>
                $this->investment->principal_amount,

            'currency' =>
                $this->investment->currency,

            'status' =>
                $this->investment->status,
        ];
    }
}
