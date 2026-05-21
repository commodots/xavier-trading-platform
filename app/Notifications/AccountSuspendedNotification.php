<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $reason;

    public function __construct(string $reason = 'Outstanding debt limit exceeded')
    {
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
            ->subject('Your Xavier Account Has Been Suspended')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your account has been suspended. Reason: {$this->reason}")
            ->line('All trading and withdrawal activities are restricted until the outstanding balance is cleared.')
            ->action('Resolve Now', url('/wallet'))
            ->line('Contact support if you believe this is an error.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => 'Account Suspended',
            'message' => "Your account has been suspended: {$this->reason}",
            'type'    => 'account',
            'action'  => 'Resolve Now',
        ];
    }
}
