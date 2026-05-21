<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $amount;
    protected $reason;

    public function __construct(float $amount, string $reason)
    {
        $this->amount = $amount;
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Urgent: Account Billing Failure Alert')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We were unable to process your periodic subscription fee of \$" . number_format($this->amount, 2) . ".")
            ->line("Reason recorded: {$this->reason}")
            ->line('To prevent your advisory access lines or active automated trading operations from being suspended, please fund your wallet immediately.')
            ->action('View Wallet Balances', url('/dashboard/wallet'))
            ->line('If you believe this is an error, please reach out to support right away.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => 'Billing Deduction Failed',
            'message' => "Could not deduct ₦" . number_format($this->amount, 2) . " due to: {$this->reason}. Please fund your wallet.",
            'type'    => 'billing',
            'action'  => 'Fund Wallet',
        ];
    }
}