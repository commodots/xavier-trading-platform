<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->user;
        $notification = $this->notification;

        // Send database notification
        if (method_exists($notification, 'toDatabase')) {
            $user->notify($notification);
        }

        // Send via other channels if configured
        Notification::send($user, $notification);
    }
}