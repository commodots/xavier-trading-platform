<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $userName)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'success',
            'title' => 'Welcome to Xavier, ' . $this->userName . '! 🎉',
            'message' => "Welcome to Xavier Trading Platform! You now have access to:\n\n" .
                "📈 Trade NGX Stocks, Global Stocks, Crypto & Fixed Income\n" .
                "💰 Fund your wallet via Bank Transfer\n" .
                "📊 Real-time market data & portfolio tracking\n" .
                "🤖 AI-powered advisory & insights\n\n" .
                "To get started, here are your next steps:\n\n" .
                "1. ✅ Verify your email address\n" .
                "2. 📋 Complete your KYC verification\n" .
                "3. 🔐 Set up Two-Factor Authentication (2FA)\n" .
                "4. 💵 Fund your wallet to start trading\n" .
                "5. 📈 Explore the markets and place your first trade\n\n" .
                "Need help? Visit our Help & Support page anytime!",
            'action' => 'Go to Dashboard',
            'action_url' => '/dashboard',
            'icon' => '🎉',
        ];
    }
}