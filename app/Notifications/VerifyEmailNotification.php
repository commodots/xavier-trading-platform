<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class VerifyEmailNotification extends VerifyEmailBase implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable)
    {
        $frontendBase = rtrim(config('app.frontend_url') ?: config('app.url') ?: '', '/');

        // Create signed backend route
        $signedUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(5), // Verification link expires after 5 minutes
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Send users to a frontend welcome screen; this page will execute verification and show success.
        return $frontendBase.'/welcome?verify_url='.urlencode($signedUrl);
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->line('Thank you for signing up with Xavier Trading! Please verify your email address to secure your account and enable trading features.')
            ->line('IMPORTANT: This verification link will expire in 5 minutes.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }
}
