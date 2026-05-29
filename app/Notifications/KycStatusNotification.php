<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $status;
    protected ?int $tier;
    protected ?string $reason;

    public function __construct(string $status, ?int $tier = null, ?string $reason = null)
    {
        $this->status = $status;
        $this->tier = $tier;
        $this->reason = $reason;
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
        $subject = 'KYC Verification Update';
        $line = 'Your identity verification status has been updated.';
        $action = 'View KYC Status';
        $url = url('/profile/kyc');

        if ($this->status === 'pending') {
            $subject = 'KYC Verification Started';
            $line = 'We have received your documents and started the verification process. We will notify you when it is complete.';
        } elseif ($this->status === 'verified' || $this->status === 'approved') {
            $subject = 'KYC Verified Successfully';
            $line = 'Your identity verification is complete.' . ($this->tier ? " You have been upgraded to Tier {$this->tier}." : '');
        } elseif ($this->status === 'rejected') {
            $subject = 'KYC Verification Rejected';
            $line = 'We could not complete your verification. ' . ($this->reason ? "Reason: {$this->reason}." : 'Please review your documents and try again.');
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->first_name},")
            ->line($line)
            ->action($action, $url)
            ->line('If you need help, contact customer support.');
    }

    public function toArray($notifiable): array
    {
        $title = 'KYC Update';
        $message = 'Your KYC status has changed.';
        $type = 'kyc';

        if ($this->status === 'pending') {
            $title = 'KYC Started';
            $message = 'Your verification is now pending. We will notify you once the review is complete.';
        } elseif ($this->status === 'verified' || $this->status === 'approved') {
            $title = 'KYC Verified';
            $message = 'Your verification is complete.' . ($this->tier ? " Tier {$this->tier} has been applied." : '');
        } elseif ($this->status === 'rejected') {
            $title = 'KYC Rejected';
            $message = 'Your verification could not be completed.' . ($this->reason ? " Reason: {$this->reason}." : '');
        }

        return [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'status' => $this->status,
            'tier' => $this->tier,
            'reason' => $this->reason,
        ];
    }
}
