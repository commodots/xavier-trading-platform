<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send email notification
     */
    public static function sendEmail(User $user, $notification): void
    {
        Notification::send($user, $notification);
    }

    /**
     * Send database notification
     */
    public static function sendDatabase(User $user, $notification): void
    {
        $notification->toDatabase($user);
        $user->notify($notification);
    }

    /**
     * Send push notification
     */
    public static function sendPush(User $user, $notification): void
    {
        Notification::send($user, $notification);
    }

    /**
     * Send SMS notification
     */
    public static function sendSMS(User $user, $message): void
    {
        // Integrate with SMS provider (e.g., Twilio, Africa's Talking)
        // This is a placeholder for SMS integration
        logger()->info("SMS to {$user->phone}: {$message}");
    }

    /**
     * Send notification via multiple channels
     */
    public static function send(User $user, $notification, array $channels = ['database']): void
    {
        foreach ($channels as $channel) {
            match ($channel) {
                'email' => self::sendEmail($user, $notification),
                'database' => self::sendDatabase($user, $notification),
                'push' => self::sendPush($user, $notification),
                'sms' => self::sendSMS($user, $notification->getSMSMessage()),
            };
        }
    }

    /**
     * Send bulk notifications
     */
    public static function sendBulk(array $users, $notification, array $channels = ['database']): void
    {
        Notification::send($users, $notification);
    }
}