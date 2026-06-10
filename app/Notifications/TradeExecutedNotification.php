<?php

namespace App\Notifications;

use App\Models\Trade;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TradeExecutedNotification extends Notification
{
    public function __construct(private Trade $trade, private string $action)
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
        $actionText = $this->action === 'open' ? 'opened' : 'closed';
        return (new MailMessage)
            ->subject("Trade Executed: {$this->trade->pair}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your trade for {$this->trade->pair} has been successfully {$actionText}.")
            ->line("Quantity: {$this->trade->quantity}")
            ->line("Price: {$this->trade->entry_price}")
            ->action('View Portfolio', url('/dashboard/portfolio'));
    }

    public function toArray($notifiable): array
    {
        $pair = $this->trade->pair;
        $actionText = $this->action === 'open' ? 'opened' : 'closed';
        $title = "Trade {$actionText}: {$pair}";
        $textMessage = 'Your trade was executed successfully.';

        return [
            'user_id' => $notifiable->id,
            'message' => $textMessage,
            
            'type' => 'trade',
            'title' => $title,
            'action' => 'View Trade',
            'action_url' => null,
            'icon' => $this->action === 'open' ? 'trade_open' : 'trade_close',
            'metadata' => [
                'trade_id' => $this->trade->id,
                'pair' => $this->trade->pair,
                'amount' => $this->trade->amount,
                'quantity' => $this->trade->quantity,
                'entry_price' => $this->trade->entry_price,
                'status' => $this->trade->status,
                'action' => $this->action,
            ],
        ];
    }
}