<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $daysLeft;

    public function __construct(int $daysLeft)
    {
        $this->daysLeft = $daysLeft;
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
            ->subject('Your Premium Advisory Trial is Ending Soon')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your free trial for our premium market advisory tier expires in {$this->daysLeft} days.")
            ->line('Keep receiving real-time top AI market picks, model portfolios, and institutional analysis without interruption.')
            ->action('Upgrade/Fund Wallet', url('/dashboard/settings/advisory'))
            ->line('Thank you for trading with us!');
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => 'Trial Period Ending',
            'message' => "Your premium advisory trial expires in {$this->daysLeft} days. Upgrade to keep access.",
            'type'    => 'warning',
            'action'  => 'Upgrade',
        ];
    }
}